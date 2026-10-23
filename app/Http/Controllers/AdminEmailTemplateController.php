<?php



namespace App\Http\Controllers;



use App\Models\EmailTemplate;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\View\View;



/** Paramétrage MOA des modèles e-mail institutionnels (N-P2). */

class AdminEmailTemplateController extends Controller

{

    public function index(Request $request): View

    {

        abort_unless($request->user()->can('notifications.admin'), 403);



        return view('admin.notifications.templates.index', [

            'templates' => EmailTemplate::with('notificationType')->orderBy('code')->paginate(20),

        ]);

    }



    public function edit(Request $request, EmailTemplate $template): View

    {

        abort_unless($request->user()->can('notifications.admin'), 403);



        return view('admin.notifications.templates.form', compact('template'));

    }



    public function update(Request $request, EmailTemplate $template): RedirectResponse

    {

        abort_unless($request->user()->can('notifications.admin'), 403);



        $data = $request->validate([

            'libelle' => 'required|string|max:255',

            'sujet' => 'required|string|max:255',

            'corps_html' => 'required|string',

            'corps_texte' => 'nullable|string',

            'actif' => 'nullable|boolean',

        ]);



        $template->update([

            ...$data,

            'actif' => $request->boolean('actif'),

        ]);



        return redirect()->route('admin.notifications.templates.index')

            ->with('succes', 'Modèle e-mail mis à jour.');

    }

}

