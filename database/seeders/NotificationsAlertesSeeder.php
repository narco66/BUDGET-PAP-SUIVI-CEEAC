<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\NotificationChannel;
use App\Models\NotificationTemplate;
use App\Models\NotificationType;
use App\Models\RelanceRule;
use App\Models\SmsTemplate;
use App\Support\NotificationCriticite;
use Illuminate\Database\Seeder;

/** Phase N-P0 — types et templates par défaut du référentiel notifications. */
class NotificationsAlertesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(NotificationsAlertesPermissionsSeeder::class);

        $types = [
            ['workflow.etape_en_attente', 'workflow', 'Validation workflow en attente', NotificationCriticite::ATTENTION],
            ['workflow.relance_sla', 'workflow', 'Relance SLA workflow', NotificationCriticite::AVERTISSEMENT],
            ['workflow.decision', 'workflow', 'Décision workflow (validation/rejet)', NotificationCriticite::INFORMATION],
            ['pap.soumission', 'pap', 'PAP soumis à validation', NotificationCriticite::ATTENTION],
            ['pap.decision', 'pap', 'Décision sur le PAP', NotificationCriticite::INFORMATION],
            ['pap.publication', 'pap', 'PAP publié', NotificationCriticite::INFORMATION],
            ['budget.soumission', 'budget', 'Budget soumis à validation', NotificationCriticite::ATTENTION],
            ['budget.decision', 'budget', 'Décision sur le budget', NotificationCriticite::INFORMATION],
            ['budget.adoption', 'budget', 'Budget adopté', NotificationCriticite::INFORMATION],
            ['ged.document', 'ged', 'Événement documentaire GED', NotificationCriticite::INFORMATION],
            ['ged.expiration', 'ged', 'Document proche expiration', NotificationCriticite::AVERTISSEMENT],
            ['ged.acces_critique', 'ged', 'Accès refusé critique', NotificationCriticite::CRITIQUE],
            ['rbm.mesure', 'rbm', 'Mesure IOV vérifiée/validée', NotificationCriticite::INFORMATION],
            ['recettes.relance', 'recettes', 'Relance recouvrement', NotificationCriticite::AVERTISSEMENT],
            ['alerte.institutionnelle', 'systeme', 'Nouvelle alerte institutionnelle', NotificationCriticite::ATTENTION],
            ['systeme.information', 'systeme', 'Information système', NotificationCriticite::INFORMATION],
            ['depense.besoin_soumis', 'depense', 'Besoin soumis à validation', NotificationCriticite::ATTENTION],
            ['depense.engagement_soumis', 'depense', 'Engagement soumis à validation', NotificationCriticite::ATTENTION],
            ['depense.paiement_valide', 'depense', 'Paiement validé', NotificationCriticite::INFORMATION],
            ['recettes.encaissement_soumis', 'recettes', 'Encaissement à valider', NotificationCriticite::ATTENTION],
            ['suivi_eval.recommandation', 'suivi_eval', 'Nouvelle recommandation', NotificationCriticite::ATTENTION],
            ['reporting.rapport_disponible', 'reporting', 'Rapport disponible', NotificationCriticite::INFORMATION],
        ];

        foreach ($types as [$code, $module, $libelle, $criticite]) {
            $type = NotificationType::updateOrCreate(
                ['code' => $code],
                [
                    'module' => $module,
                    'libelle' => $libelle,
                    'criticite_defaut' => $criticite,
                    'canal_defaut' => 'database',
                    'actif' => true,
                ]
            );

            NotificationTemplate::updateOrCreate(
                [
                    'notification_type_id' => $type->id,
                    'code' => 'defaut',
                    'canal' => 'database',
                ],
                [
                    'sujet' => '{{titre}}',
                    'corps' => '{{message}}',
                    'actif' => true,
                ]
            );
        }

        $this->seedRelanceRules();
        $this->seedEmailTemplates();
        $this->seedSmsTemplates();
        $this->seedNotificationChannels();
    }

    private function seedRelanceRules(): void
    {
        $rules = [
            ['pap.activite_retard', 'pap', 'Activités PAP en retard', 7, ['directeur', 'chef_service'], 'workflow.relance_sla'],
            ['budget.validation_attente', 'budget', 'Validation budget en attente', 5, ['controleur_financier', 'commissaire'], 'workflow.relance_sla'],
            ['depense.engagement_attente', 'depense', 'Engagement non traité', 5, ['controleur_financier'], 'workflow.relance_sla'],
            ['depense.liquidation_attente', 'depense', 'Liquidation en attente', 5, ['controleur_financier'], 'workflow.relance_sla'],
            ['recettes.encaissement_attente', 'recettes', 'Encaissement non validé', 7, ['controleur_financier', 'comptable'], 'recettes.relance'],
        ];

        foreach ($rules as [$code, $module, $libelle, $intervalle, $roles, $notifType]) {
            RelanceRule::updateOrCreate(
                ['code' => $code],
                [
                    'module' => $module,
                    'libelle' => $libelle,
                    'intervalle_jours' => $intervalle,
                    'roles_cibles' => $roles,
                    'notification_type_code' => $notifType,
                    'actif' => true,
                ]
            );
        }
    }

    private function seedEmailTemplates(): void
    {
        $defaults = [
            'recettes.relance' => ['Relance recouvrement — {{reference}}', '<p>{{message}}</p>'],
            'depense.besoin_soumis' => ['Besoin {{numero}}', '<p>{{message}}</p>'],
            'depense.engagement_soumis' => ['Engagement {{numero}}', '<p>{{message}}</p>'],
            'depense.paiement_valide' => ['Paiement {{numero}}', '<p>{{message}}</p>'],
            'recettes.encaissement_soumis' => ['Encaissement {{reference}}', '<p>{{message}}</p>'],
            'suivi_eval.recommandation' => ['Recommandation {{numero}}', '<p>{{libelle}}</p>'],
            'reporting.rapport_disponible' => ['Rapport {{reference}}', '<p>{{titre}}</p>'],
            'workflow.relance_sla' => ['{{titre}}', '<p>{{message}}</p>'],
            'alerte.institutionnelle' => ['{{titre}}', '<p>{{message}}</p>'],
        ];

        foreach ($defaults as $code => [$sujet, $html]) {
            $type = NotificationType::where('code', $code)->first();
            EmailTemplate::updateOrCreate(
                ['code' => $code],
                [
                    'notification_type_id' => $type?->id,
                    'libelle' => $type?->libelle ?? $code,
                    'sujet' => $sujet,
                    'corps_html' => $html,
                    'corps_texte' => strip_tags($html),
                    'actif' => true,
                ]
            );
        }
    }

    private function seedSmsTemplates(): void
    {
        $defaults = [
            'alerte.institutionnelle' => 'CEEAC ALERTE: {{titre}} — {{message}}',
            'ged.acces_critique' => 'CEEAC URGENT: accès critique GED — {{message}}',
            'recettes.relance' => 'CEEAC recouvrement {{reference}} — dû {{montant_du}} XAF',
        ];

        foreach ($defaults as $code => $corps) {
            $type = NotificationType::where('code', $code)->first();
            SmsTemplate::updateOrCreate(
                ['code' => $code],
                [
                    'notification_type_id' => $type?->id,
                    'libelle' => $type?->libelle ?? $code,
                    'corps' => $corps,
                    'actif' => true,
                ]
            );
        }
    }

    private function seedNotificationChannels(): void
    {
        $matrix = [
            'alerte.institutionnelle' => ['database', 'email', 'sms'],
            'ged.acces_critique' => ['database', 'email', 'sms'],
            'workflow.relance_sla' => ['database', 'email'],
            'recettes.relance' => ['database', 'email'],
            'depense.besoin_soumis' => ['database', 'email'],
            'reporting.rapport_disponible' => ['database', 'email'],
        ];

        foreach ($matrix as $code => $canaux) {
            $type = NotificationType::where('code', $code)->first();
            if (! $type) {
                continue;
            }
            foreach ($canaux as $ordre => $canal) {
                NotificationChannel::updateOrCreate(
                    ['notification_type_id' => $type->id, 'canal' => $canal],
                    ['actif' => true, 'ordre' => $ordre + 1],
                );
            }
        }
    }
}
