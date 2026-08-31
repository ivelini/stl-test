<?php

namespace App\Console\Commands;

use App\Enums\HoldStatusEnum;
use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Проверка характеристики 1 на живом стеке (план, тест 20):
 * N удержаний через API → параллельные confirm через curl → сводка и сверка с БД.
 */
class HoldsStressCommand extends Command
{
    protected $signature = 'holds:stress {slot_id} {requests=200} {--concurrency=20}';

    protected $description = 'Конкурентная проверка подтверждений: N confirm на слот, сверка confirmed с capacity';

    public function handle(): int
    {
        $slotId = (int) $this->argument('slot_id');
        $requests = (int) $this->argument('requests');
        $concurrency = (int) $this->option('concurrency');

        $slot = Slot::findOrFail($slotId);
        $base = config('services.stress.api_base');
        $tmp = tempnam(sys_get_temp_dir(), 'holds');

        if ($slot->holds()->where('status', HoldStatusEnum::Confirmed->value)->exists()) {
            $this->warn('На слоте уже есть подтверждённые удержания — результат будет искажён, используйте свежий слот.');
        }

        try {
            $holdIds = $this->createHolds($slotId, $requests, $base);
            $codes = $this->confirmInParallel($holdIds, $base, $concurrency, $tmp);

            $this->summarize($codes);

            return $this->verify($slot, $codes);
        } finally {
            @unlink($tmp);
        }
    }

    /** @return array<int, int> */
    private function createHolds(int $slotId, int $requests, string $base): array
    {
        $this->info("Создание {$requests} удержаний...");

        $ids = [];

        for ($i = 0; $i < $requests; $i++) {
            $response = Http::withHeaders(['Idempotency-Key' => (string) Str::uuid()])
                ->post("{$base}/slots/{$slotId}/hold");

            $response->throw();

            $ids[] = (int) $response->json('id');
        }

        return $ids;
    }

    /**
     * @param  array<int, int>  $holdIds
     * @return array<int, string>
     */
    private function confirmInParallel(array $holdIds, string $base, int $concurrency, string $tmp): array
    {
        $this->info("Параллельный confirm ({$concurrency} потоков)...");

        $urls = array_map(fn (int $id) => "{$base}/holds/{$id}/confirm", $holdIds);
        file_put_contents($tmp, implode("\n", $urls)."\n");

        $result = Process::run(
            "xargs -P {$concurrency} -I{} curl -s -X POST -o /dev/null -w '%{http_code}\n' {} < {$tmp}"
        );

        return array_filter(explode("\n", trim($result->output())));
    }

    /** @param array<int, string> $codes */
    private function summarize(array $codes): void
    {
        $counts = array_count_values($codes);

        foreach ($counts as $code => $count) {
            $this->line("  {$code}: {$count}");
        }
    }

    /**
     * @param  array<int, string>  $codes
     * @return int Код выхода: 0 при confirmed == min(capacity, requests), иначе 1
     */
    private function verify(Slot $slot, array $codes): int
    {
        $confirmed = Hold::where('slot_id', $slot->id)
            ->where('status', HoldStatusEnum::Confirmed->value)
            ->count();

        $expected = min($slot->capacity, count($codes));

        if ($confirmed === $expected) {
            $this->info("OK: confirmed = {$confirmed}, ожидалось = {$expected}");

            return self::SUCCESS;
        }

        $this->error("FAIL: confirmed = {$confirmed}, ожидалось = {$expected}");

        return self::FAILURE;
    }
}
