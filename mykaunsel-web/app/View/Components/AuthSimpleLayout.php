<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AuthSimpleLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public string $contentMaxWidth = '360px',
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.auth-simple');
    }
}
