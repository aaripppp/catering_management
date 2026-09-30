<?php

namespace App\Livewire;

use Livewire\Component;

class DeleteConfirmationModal extends Component
{
    public bool $open = false;

    public ?int $recordId = null;

    public string $recordName = '';

    public string $message = '';

    public string $confirmAction = '';

    public array $actionParams = [];

    #[On('confirm-delete')]
    public function openModal(int $recordId, string $recordName, string $message, string $confirmAction, array $actionParams = []): void
    {
        $this->recordId = $recordId;
        $this->recordName = $recordName;
        $this->message = $message;
        $this->confirmAction = $confirmAction;
        $this->actionParams = $actionParams;
        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
        $this->reset(['recordId', 'recordName', 'message', 'confirmAction', 'actionParams']);
    }

    public function confirm(): void
    {
        if ($this->confirmAction && $this->recordId !== null) {
            $this->dispatch($this->confirmAction, $this->recordId, ...$this->actionParams);
        }
        $this->closeModal();
    }

    public function render()
    {
        return view('livewire.delete-confirmation-modal');
    }
}
