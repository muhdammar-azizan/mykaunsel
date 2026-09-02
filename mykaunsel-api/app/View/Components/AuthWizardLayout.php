<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AuthWizardLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public string $imageSlot = 'auth-visual',
        public string $asideHeadline = '',
        public string $asideSubtext = '',
        public string $contentMaxWidth = '460px',
        public ?string $backHref = null,
        public string $backLabel = 'Back',
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.auth-wizard');
    }
}
