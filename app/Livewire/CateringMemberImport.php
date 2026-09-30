<?php

namespace App\Livewire;

use App\Models\CateringMember;
use App\Services\CateringMemberImportService;
use App\Services\CateringMemberImportSpreadsheet;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class CateringMemberImport extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    public int $step = 1;

    /** @var array<int, array<string, mixed>> */
    public array $importRows = [];

    /** @var array<string, mixed> */
    public array $preview = [];

    /** @var array<string, mixed> */
    public array $result = [];

    public function downloadTemplate(CateringMemberImportSpreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('create', CateringMember::class);

        $path = $spreadsheet->createTemplate();

        return response()
            ->download($path, 'template-import-peserta-catering.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function previewImport(
        CateringMemberImportSpreadsheet $spreadsheet,
        CateringMemberImportService $importService,
    ): void {
        Gate::authorize('create', CateringMember::class);

        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:12288'],
        ], [
            'file.required' => 'File XLSX wajib dipilih.',
            'file.mimes' => 'File harus berformat .xlsx.',
            'file.max' => 'Ukuran file maksimal 12 MB.',
        ]);

        try {
            $this->importRows = $spreadsheet->read($this->file->getRealPath());

            if ($this->importRows === []) {
                $this->addError('file', 'File tidak memiliki data peserta.');

                return;
            }

            $this->preview = $importService->preview($this->importRows);
            $this->step = 2;
            $this->reset('file');
        } catch (RuntimeException $exception) {
            $this->addError('file', $exception->getMessage());
        }
    }

    public function confirmImport(CateringMemberImportService $importService): void
    {
        Gate::authorize('create', CateringMember::class);

        if ($this->importRows === [] || ($this->preview['has_errors'] ?? true)) {
            $this->addError('import', 'Import tidak dapat diproses selama masih ada baris bermasalah.');

            return;
        }

        try {
            $this->result = $importService->import($this->importRows);
            $this->step = 3;
            $this->importRows = [];
            $this->preview = [];
            $this->dispatch(
                'toast',
                type: 'success',
                message: 'Peserta catering berhasil diimport.',
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('import', 'Import gagal dan seluruh perubahan telah dibatalkan.');
        }
    }

    public function backToSetup(): void
    {
        $this->step = 1;
        $this->importRows = [];
        $this->preview = [];
        $this->result = [];
        $this->reset('file');
        $this->resetValidation();
    }

    public function render(): View
    {
        Gate::authorize('create', CateringMember::class);

        return view('livewire.catering-member-import');
    }
}
