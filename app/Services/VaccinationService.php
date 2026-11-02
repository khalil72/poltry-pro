<?php
namespace App\Services;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;

class VaccinationService
{
    /** Creates scheduled records for a new batch from the vaccination programme. */
    public static function scheduleForBatch(int $batchId): void
    {
        $b = DB::one('SELECT * FROM batches WHERE id=?', [$batchId]);
        $plans = DB::all("SELECT * FROM vaccinations WHERE deleted_at IS NULL AND (batch_type='all' OR batch_type=?)", [$b['batch_type']]);
        foreach ($plans as $v) {
            $offset = (int) $v['age_days'] - (int) $b['age_at_purchase_days'];
            if ($offset < 0) {
                continue;
            }
            DB::insert('vaccination_records', [
                'batch_id' => $batchId, 'farm_id' => $b['farm_id'], 'vaccination_id' => $v['id'],
                'scheduled_date' => date('Y-m-d', strtotime($b['purchase_date'] . " +$offset days")), 'created_by' => Auth::id(),
            ]);
        }
    }

    public static function schedule(array $d): int
    {
        $b = DB::one('SELECT farm_id FROM batches WHERE id=?', [$d['batch_id']]);
        if (!$b) {
            throw new BusinessRuleException('Choose a batch.');
        }
        Auth::assertFarm((int) $b['farm_id']);
        return DB::insert('vaccination_records', [
            'batch_id' => $d['batch_id'], 'farm_id' => $b['farm_id'], 'vaccination_id' => $d['vaccination_id'],
            'scheduled_date' => $d['scheduled_date'], 'veterinarian' => $d['veterinarian'] ?? null, 'notes' => $d['notes'] ?? null, 'created_by' => Auth::id(),
        ]);
    }

    /** Marks a dose as given, uses the stock if a medicine is linked, and books the next dose if the plan repeats. */
    public static function markGiven(int $id, array $d): void
    {
        DB::transaction(function () use ($id, $d) {
            $r = DB::one('SELECT * FROM vaccination_records WHERE id=? FOR UPDATE', [$id]);
            if (!$r || !in_array($r['status'], ['scheduled', 'missed'], true)) {
                throw new BusinessRuleException('This vaccination has already been closed.');
            }
            Auth::assertFarm((int) $r['farm_id']);
            $date = $d['given_date'] ?: date('Y-m-d');
            if ($date > date('Y-m-d')) {
                throw new BusinessRuleException('The date given cannot be in the future.');
            }
            $v = DB::one('SELECT * FROM vaccinations WHERE id=?', [$r['vaccination_id']]);
            $doses = (float) ($d['doses'] ?? 0);
            $usageId = null;
            if ($v['medicine_id'] && $doses > 0) {
                $usageId = OperationsService::postMedicine((int) $r['batch_id'], (int) $r['farm_id'], (int) $v['medicine_id'], $doses, $date, $d['veterinarian'] ?? null, 'Vaccination: ' . $v['name'], null);
            }
            $next = $v['repeat_days'] ? date('Y-m-d', strtotime("$date +{$v['repeat_days']} days")) : null;
            DB::update('vaccination_records', [
                'status' => 'given', 'given_date' => $date, 'doses' => $doses ?: null, 'veterinarian' => ($d['veterinarian'] ?? null) ?: $r['veterinarian'],
                'next_date' => $next, 'usage_id' => $usageId, 'notes' => ($d['notes'] ?? null) ?: $r['notes'],
            ], $id);
            if ($next) {
                DB::insert('vaccination_records', ['batch_id' => $r['batch_id'], 'farm_id' => $r['farm_id'], 'vaccination_id' => $r['vaccination_id'], 'scheduled_date' => $next, 'created_by' => Auth::id()]);
            }
            Activity::log('vaccinate', 'vaccination_record', $id, $v['name']);
        });
    }

    public static function cancel(int $id, ?string $reason = null): void
    {
        DB::transaction(function () use ($id, $reason) {
            $r = DB::one('SELECT * FROM vaccination_records WHERE id=? FOR UPDATE', [$id]);
            if (!$r || $r['status'] === 'cancelled') {
                throw new BusinessRuleException('This vaccination is already cancelled.');
            }
            Auth::assertFarm((int) $r['farm_id']);
            if ($r['usage_id']) {
                OperationsService::cancelMedicine((int) $r['usage_id'], 'Vaccination cancelled');
            }
            DB::update('vaccination_records', ['status' => 'cancelled'], $id);
            Activity::log('cancel', 'vaccination_record', $id, $reason);
        });
    }
}
