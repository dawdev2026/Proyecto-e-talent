<?php
declare(strict_types=1);

final class DashboardController extends Controller
{
    private CompanyModel $companies;
    private UserModel $users;
    private ProfileModel $profiles;
    private UserFieldModel $userFields;
    private TestProcessModel $testProcesses;
    private InterviewProcessModel $interviews;

    public function __construct(
        ?Template $view = null,
        ?CompanyModel $companies = null,
        ?UserModel $users = null,
        ?ProfileModel $profiles = null,
        ?UserFieldModel $userFields = null,
        ?TestProcessModel $testProcesses = null,
        ?InterviewProcessModel $interviews = null
    ) {
        parent::__construct($view);
        $this->companies = $companies ?: new CompanyModel();
        $this->users = $users ?: new UserModel();
        $this->profiles = $profiles ?: new ProfileModel();
        $this->userFields = $userFields ?: new UserFieldModel();
        $this->testProcesses = $testProcesses ?: new TestProcessModel();
        $this->interviews = $interviews ?: new InterviewProcessModel();
    }

    public function index(): void
    {
        require_auth();

        $user = current_user() ?: [];
        $processes = $this->testProcesses->allForUser($user);
        $today = date('Y-m-d');
        $now = time();
        $agenda = [];

        foreach ($processes as $process) {
            $startsAt = trim((string) ($process['starts_at'] ?? ''));
            $endsAt = trim((string) ($process['ends_at'] ?? ''));
            if ($startsAt === '' || $endsAt === '') {
                continue;
            }

            $startTimestamp = strtotime($startsAt);
            $endTimestamp = strtotime($endsAt);
            if ($startTimestamp === false || $endTimestamp === false
                || substr($startsAt, 0, 10) > $today
                || substr($endsAt, 0, 10) < $today
                || in_array((string) ($process['status'] ?? ''), ['cancelled', 'closed'], true)) {
                continue;
            }

            $agenda[] = [
                'kind' => 'process',
                'date' => date('d/m/Y', $startTimestamp),
                'start_at' => $startsAt,
                'end_at' => $endsAt,
                'title' => (string) ($process['name'] ?? 'Proceso de evaluación'),
                'category' => 'Proceso de evaluación',
                'status_label' => $now >= $startTimestamp && $now <= $endTimestamp ? 'En curso' : 'Programado',
                'action_label' => 'Abrir proceso',
                'action_route' => 'test-process.show',
                'action_id' => (int) ($process['id'] ?? 0),
                'icon' => 'bi-clipboard-check',
            ];
        }

        $canSeeAllInterviews = has_permission('manage_interview_processes')
            || has_permission('conduct_selection_interviews')
            || has_permission('view_interview_reports');
        try {
            $interviewAgenda = $this->interviews->agendaForUser((int) ($user['id'] ?? 0), $canSeeAllInterviews);
        } catch (Throwable $exception) {
            error_log('Dashboard interview agenda error: ' . $exception->getMessage());
            $interviewAgenda = [];
        }

        foreach ($interviewAgenda as $interview) {
            $startTimestamp = strtotime((string) ($interview['scheduled_start_at'] ?? ''));
            $endTimestamp = strtotime((string) ($interview['scheduled_end_at'] ?? ''));
            if ($startTimestamp === false || $endTimestamp === false) {
                continue;
            }

            $agenda[] = [
                'kind' => 'interview',
                'date' => date('d/m/Y', $startTimestamp),
                'start_at' => (string) $interview['scheduled_start_at'],
                'end_at' => (string) $interview['scheduled_end_at'],
                'title' => 'Entrevista — ' . (string) ($interview['candidate_name'] ?? 'Participante'),
                'category' => 'Entrevista',
                'status_label' => (string) ($interview['meeting_status'] ?? '') === 'finished'
                    ? 'Finalizada'
                    : (in_array((string) ($interview['meeting_status'] ?? ''), ['in_progress', 'started'], true) ? 'En curso' : 'Programado'),
                'action_label' => (string) ($interview['meeting_status'] ?? '') === 'finished' ? 'Ver entrevista' : 'Ingresar a sala',
                'action_route' => 'interview-appointment.room',
                'action_id' => (int) ($interview['id'] ?? 0),
                'icon' => 'bi-camera-video',
            ];
        }

        usort($agenda, static fn(array $left, array $right): int => strcmp((string) $left['start_at'], (string) $right['start_at']));

        $macro = $this->testProcesses->dashboardMacroForUser($user);
        $processOverview = $this->testProcesses->dashboardProcessOverviewForUser($user);
        $evaluationsTotal = (int) ($macro['evaluations_total'] ?? 0);
        $evaluationsAnswered = (int) ($macro['evaluations_answered'] ?? 0);
        $peopleAssigned = (int) ($processOverview['completed_assigned_people'] ?? 0);
        $peopleCompleted = (int) ($processOverview['completed_finished_people'] ?? 0);
        $peopleNotStarted = (int) ($processOverview['completed_not_started_people'] ?? 0);
        $interviewsToday = count(array_filter($agenda, static fn(array $item): bool => $item['kind'] === 'interview'));

        $this->render('dashboard/index', [
            'title' => 'Dashboard | e-talent',
            'currentPage' => 'dashboard',
            'agenda' => $agenda,
            'processOverview' => $processOverview,
            'peopleAssigned' => $peopleAssigned,
            'peopleCompleted' => $peopleCompleted,
            'peoplePartial' => max(0, $peopleAssigned - $peopleCompleted - $peopleNotStarted),
            'peopleNotStarted' => $peopleNotStarted,
            'interviewsToday' => $interviewsToday,
            'pendingEvaluations' => max(0, $evaluationsTotal - $evaluationsAnswered),
            'macro' => $macro,
            'macroProgress' => $evaluationsTotal > 0 ? (int) round(($evaluationsAnswered / $evaluationsTotal) * 100) : 0,
        ]);
    }
}
