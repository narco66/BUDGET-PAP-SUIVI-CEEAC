@php
    $categories = \App\Support\WorkflowRejetCategorie::libelles();
@endphp
<div class="card border-0 shadow-sm mt-3 border-start border-4 border-warning">
    <div class="card-body">
        <h6 class="fw-bold">Votre décision</h6>
        <form action="{{ $action }}" method="POST">@csrf
            <select name="decision" class="form-select mb-2 decision-workflow">
                <option value="valider">Valider</option>
                @if ($avecRenvoi ?? true)<option value="renvoyer">Renvoyer pour correction</option>@endif
                <option value="rejeter">Rejeter</option>
            </select>
            <select name="categorie_rejet" class="form-select mb-2 categorie-rejet d-none">
                <option value="">— Catégorie de rejet —</option>
                @foreach ($categories as $code => $lib)
                    <option value="{{ $code }}">{{ $lib }}</option>
                @endforeach
            </select>
            <textarea name="observation" class="form-control mb-2" rows="2" placeholder="Observation (obligatoire si renvoi/rejet)"></textarea>
            <button class="btn btn-warning w-100">Enregistrer la décision</button>
        </form>
    </div>
</div>
@once
    @push('scripts')
    <script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
        document.querySelectorAll('.decision-workflow').forEach(function (sel) {
            const form = sel.closest('form');
            const cat = form.querySelector('.categorie-rejet');
            const toggle = () => {
                if (!cat) return;
                cat.classList.toggle('d-none', sel.value !== 'rejeter');
                cat.required = sel.value === 'rejeter';
            };
            sel.addEventListener('change', toggle);
            toggle();
        });
    </script>
    @endpush
@endonce
