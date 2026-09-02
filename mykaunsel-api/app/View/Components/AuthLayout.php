<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AuthLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public string $imageSlot = 'auth-visual',
        public string $asideTitle = '',
        public string $asideSubtitle = '',
        public string $contentMaxWidth = '400px',
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.auth');
    }
}
