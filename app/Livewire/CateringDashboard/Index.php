<?php

namespace App\Livewire\CateringDashboard;

use App\Services\CateringAttendanceInitializerService;
use App\Services\CateringRequirementRecapService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Operational recap of how many catering portions a chosen date needs.
 *
 * Missing defaults are persisted globally before this screen summarises the
 * selected date, so the result does not depend on opening attendance contexts.
 */
class Index extends Component
{
    /** The operational date being recap-ed, formatted as Y-m-d. */
    public string $selectedDate = '';

    /**
     * The whole recap snapshot for the selected date.
     *
     * Locked and rebuilt only when the date changes, so opening a detail modal
     * or any other interaction never re-runs the recap queries.
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $recap = [];

    /** Key of the open detail group, or null when the modal is closed. */
    #[Locked]
    public ?string $detailKey = null;

    /**
     * Heading lines for the open detail group.
     *
     * @var array<string, string>
     */
    #[Locked]
    public array $detailHeading = [];

    /**
     * The counted participants behind the open detail group.
     *
     * @var array<int, array{id: int, name: string, type: string, status: string}>
     */
    #[Locked]
    public array $detailRows = [];

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);

        $this->selectedDate = $this->businessToday()->addDay()->toDateString();

        $this->refreshRecap();
    }

    public function gotoYesterday(): void
    {
        $this->applyDate($this->businessToday()->subDay());
    }

    public function gotoToday(): void
    {
        $this->applyDate($this->businessToday());
    }

    public function gotoTomorrow(): void
    {
        $this->applyDate($this->businessToday()->addDay());
    }

    /**
     * Applied from the native date picker. Validation lives here instead of on a
     * bound model so a half typed date in the picker never raises an error.
     */
    public function changeDate(string $value): void
    {
        abort_unless(auth()->check(), 403);

        // Validate the incoming value, not the current property, so a rejected
        // picker value never reaches the recap and never becomes the selection.
        Validator::make(
            ['selectedDate' => $value],
            ['selectedDate' => ['required', 'date_format:Y-m-d']],
            ['selectedDate.date_format' => 'Tanggal tidak valid.'],
        )->validate();

        $this->selectedDate = $value;

        $this->refreshRecap();
    }

    public function openDetail(string $key): void
    {
        abort_unless(auth()->check(), 403);

        if (! array_key_exists($key, $this->recap['details'] ?? [])) {
            return;
        }

        $this->detailHeading = $this->recap['detailHeadings'][$key] ?? [];
        $this->detailRows = $this->recap['details'][$key];
        $this->detailKey = $key;
    }

    public function closeDetail(): void
    {
        $this->detailKey = null;
        $this->detailHeading = [];
        $this->detailRows = [];
    }

    /**
     * Keep the recap in step with attendance that other users are saving.
     *
     * Unlike a date change this keeps an open detail modal on screen and
     * refreshes its rows, so a poll never yanks the modal out from under the user.
     */
    public function pollRecap(): void
    {
        abort_unless(auth()->check(), 403);

        $openDetailKey = $this->detailKey;
        $this->recap = $this->recapForSelectedDate();

        if ($openDetailKey === null || ! array_key_exists($openDetailKey, $this->recap['details'] ?? [])) {
            $this->closeDetail();

            return;
        }

        $this->detailHeading = $this->recap['detailHeadings'][$openDetailKey] ?? [];
        $this->detailRows = $this->recap['details'][$openDetailKey];
    }

    public function render(): View
    {
        return view('livewire.catering-dashboard.index', [
            'today' => $this->businessToday()->toDateString(),
            'yesterday' => $this->businessToday()->subDay()->toDateString(),
            'tomorrow' => $this->businessToday()->addDay()->toDateString(),
        ]);
    }

    private function applyDate(CarbonImmutable $date): void
    {
        abort_unless(auth()->check(), 403);

        $this->selectedDate = $date->toDateString();

        $this->refreshRecap();
    }

    private function refreshRecap(): void
    {
        $this->closeDetail();

        $this->recap = $this->recapForSelectedDate();
    }

    /**
     * @return array<string, mixed>
     */
    private function recapForSelectedDate(): array
    {
        $date = CarbonImmutable::parse($this->selectedDate, config('app.timezone'))->startOfDay();

        CateringAttendanceInitializerService::ensureDate($date);

        return CateringRequirementRecapService::forDate($date);
    }

    /**
     * Today in the timezone the rest of the application uses.
     *
     * The catering attendance matrix anchors its month on `now()`, so the recap
     * follows the same configured application timezone instead of assuming UTC.
     */
    private function businessToday(): CarbonImmutable
    {
        return CarbonImmutable::today(config('app.timezone'));
    }
}
