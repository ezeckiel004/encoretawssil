<?php

namespace App\Events;

use App\Models\Navette;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NavetteTerminee
{
    use Dispatchable, SerializesModels;

    public $navette;

    /**
     * Create a new event instance.
     */
    public function __construct(Navette $navette)
    {
        $this->navette = $navette;
    }
}
