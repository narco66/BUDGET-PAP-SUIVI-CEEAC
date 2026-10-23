<?php

namespace App\View\Components\Navigation;

use App\Services\Navigation\MenuService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class SidebarMenu extends Component
{
    /** @param  list<array<string, mixed>>  $items */
    public function __construct(public array $items = [], public bool $nested = false)
    {
        if ($this->items === []) {
            $this->items = app(MenuService::class)->build();
        }
    }

    public function render(): View
    {
        return view('components.navigation.sidebar-menu');
    }
}
