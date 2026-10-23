<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function __construct(private readonly UserService $service)
    {
    }

    public function edit(): View
    {
        return view('auth.password', ['force' => auth()->user()->doit_changer_mot_de_passe]);
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $this->service->definirMotDePasse($request->user(), $request->validated('password'));

        return redirect()->route('dashboard')->with('succes', 'Mot de passe mis à jour.');
    }
}
