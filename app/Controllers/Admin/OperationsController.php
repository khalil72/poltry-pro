<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database as DB;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Services\VaccinationService;

/** Small screens that don't fit the generic module pattern. */
class OperationsController extends AdminController
{
    public function vaccinationGivenForm(string $id): void
    {
        $r = DB::one('SELECT r.*, v.name vaccine_name, v.medicine_id, m.name medicine_name, m.unit, b.batch_code FROM vaccination_records r JOIN vaccinations v ON v.id=r.vaccination_id JOIN batches b ON b.id=r.batch_id LEFT JOIN medicines m ON m.id=v.medicine_id WHERE r.id=?' . Auth::farmSql('r.farm_id'), [(int) $id]);
        if (!$r) {
            throw new NotFoundException();
        }
        $birds = (int) DB::value('SELECT COALESCE(SUM(quantity),0) FROM bird_inventory WHERE batch_id=?', [$r['batch_id']]);
        $this->view('admin/operations/vaccination_given', ['title' => 'Mark vaccination as given', 'rec' => $r, 'birds' => $birds]);
    }

    public function vaccinationGiven(string $id): void
    {
        VaccinationService::markGiven((int) $id, [
            'given_date' => Request::input('given_date'),
            'doses' => is_numeric(Request::input('doses')) ? (float) Request::input('doses') : 0,
            'veterinarian' => mb_substr((string) Request::input('veterinarian', ''), 0, 120) ?: null,
            'notes' => mb_substr((string) Request::input('notes', ''), 0, 255) ?: null,
        ]);
        flash('success', 'Vaccination marked as given.');
        redirect('admin/vaccination-records');
    }

    public function attendanceForm(): void
    {
        $date = (string) Request::query('date', date('Y-m-d'));
        if (!\DateTime::createFromFormat('Y-m-d', $date)) {
            $date = date('Y-m-d');
        }
        $farm = Request::query('farm_id');
        $sql = "SELECT e.id, e.name, e.employee_code, e.designation, f.name farm_name, a.status att_status, a.notes att_notes
                FROM employees e LEFT JOIN farms f ON f.id=e.farm_id LEFT JOIN attendance a ON a.employee_id=e.id AND a.attendance_date=?
                WHERE e.deleted_at IS NULL AND e.status IN ('active','on_leave')" . Auth::farmSql('e.farm_id');
        $params = [$date];
        if ($farm) {
            $sql .= ' AND e.farm_id=?';
            $params[] = (int) $farm;
        }
        $this->view('admin/operations/attendance', [
            'title' => 'Mark attendance', 'date' => $date, 'farmId' => $farm,
            'rows' => DB::all($sql . ' ORDER BY f.name, e.name', $params),
        ]);
    }

    public function attendanceSave(): void
    {
        $date = (string) Request::input('date');
        if (!\DateTime::createFromFormat('Y-m-d', $date) || $date > date('Y-m-d')) {
            throw new BusinessRuleException('Choose a valid date that is not in the future.');
        }
        $statuses = ['present', 'absent', 'leave', 'half_day'];
        $allowed = array_column(DB::all('SELECT id FROM employees WHERE deleted_at IS NULL' . Auth::farmSql('farm_id')), 'id');
        DB::transaction(function () use ($date, $statuses, $allowed) {
            foreach ((array) ($_POST['status'] ?? []) as $emp => $status) {
                if (!in_array($status, $statuses, true) || !in_array((int) $emp, array_map('intval', $allowed), true)) {
                    continue;
                }
                DB::query(
                    'INSERT INTO attendance (employee_id, attendance_date, status, notes, created_by) VALUES (?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE status=VALUES(status), notes=VALUES(notes)',
                    [(int) $emp, $date, $status, mb_substr((string) ($_POST['notes'][$emp] ?? ''), 0, 255) ?: null, Auth::id()]
                );
            }
        });
        flash('success', 'Attendance saved.');
        redirect('admin/attendance/mark?date=' . urlencode($date));
    }
}
