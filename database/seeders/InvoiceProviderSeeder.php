<?php

namespace Database\Seeders;

use App\Models\InvoiceProvider;
use Illuminate\Database\Seeder;

class InvoiceProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'Amazon (AWS)',
                'slug' => 'amazon',
                'category' => 'cloud',
                'default_currency' => 'USD',
                'company' => 'novatech/phinxlab',
                'detection_keywords' => ['Amazon', 'AWS', 'Amazon Web Services', 'Amazon Dev'],
            ],
            [
                'name' => 'Google',
                'slug' => 'google',
                'category' => 'licencias',
                'default_currency' => 'USD',
                'company' => 'novatech/phinxlab',
                'detection_keywords' => ['Google LLC', 'Google Workspace', 'GCP', 'Google Cloud'],
            ],
            [
                'name' => 'Microsoft',
                'slug' => 'microsoft',
                'category' => 'licencias',
                'default_currency' => 'ARS',
                'company' => 'novatech/phinxlab',
                'detection_keywords' => ['Microsoft Corporation', 'Microsoft 365', 'Office 365', 'Exchange Online', 'Teams'],
            ],
            [
                'name' => 'Telecom',
                'slug' => 'telecom',
                'category' => 'internet',
                'default_currency' => 'ARS',
                'company' => 'novatech',
                'detection_keywords' => ['Telecom Argentina', 'Telecom', 'Personal Flow'],
            ],
            [
                'name' => 'Metrotel',
                'slug' => 'metrotel',
                'category' => 'internet',
                'default_currency' => 'ARS',
                'company' => 'novatech',
                'detection_keywords' => ['Cps Comunicaciones', 'Metrotel', 'CPS'],
            ],
            [
                'name' => 'Net2Phone',
                'slug' => 'net2phone',
                'category' => 'telefonia',
                'default_currency' => 'ARS',
                'company' => 'novatech',
                'detection_keywords' => ['IDT CORPORATION', 'Net2Phone', 'Net2phone', 'IDT'],
            ],
            [
                'name' => 'Atlassian',
                'slug' => 'atlassian',
                'category' => 'licencias',
                'default_currency' => 'USD',
                'company' => 'phinxlab',
                'detection_keywords' => ['Atlassian', 'Jira', 'Confluence', 'Bitbucket'],
            ],
            [
                'name' => 'Monday',
                'slug' => 'monday',
                'category' => 'licencias',
                'default_currency' => 'USD',
                'company' => 'novatech/phinxlab',
                'detection_keywords' => ['monday.com', 'Monday', 'Work management'],
            ],
            [
                'name' => 'Adobe',
                'slug' => 'adobe',
                'category' => 'licencias',
                'default_currency' => 'ARS',
                'company' => 'phinxlab',
                'detection_keywords' => ['Adobe Systems', 'Adobe', 'Creative Cloud'],
            ],
            [
                'name' => 'CircleCI',
                'slug' => 'circleci',
                'category' => 'cloud',
                'default_currency' => 'USD',
                'company' => 'phinxlab',
                'detection_keywords' => ['CircleCI', 'Circle Internet Services'],
            ],
            [
                'name' => 'Cloudinary',
                'slug' => 'cloudinary',
                'category' => 'cloud',
                'default_currency' => 'USD',
                'company' => 'phinxlab',
                'detection_keywords' => ['Cloudinary'],
            ],
            [
                'name' => 'Retool',
                'slug' => 'retool',
                'category' => 'licencias',
                'default_currency' => 'USD',
                'company' => 'novatech',
                'detection_keywords' => ['Retool Inc', 'Retool'],
            ],
            [
                'name' => 'Twilio',
                'slug' => 'twilio',
                'category' => 'comunicaciones',
                'default_currency' => 'USD',
                'company' => 'novatech',
                'detection_keywords' => ['Twilio Inc', 'Twilio', 'SendGrid', 'Sendgrid'],
            ],
            [
                'name' => 'Zendesk',
                'slug' => 'zendesk',
                'category' => 'licencias',
                'default_currency' => 'USD',
                'company' => 'novatech',
                'detection_keywords' => ['Zendesk', 'Zendesk Inc'],
            ],
            [
                'name' => 'GoDaddy',
                'slug' => 'godaddy',
                'category' => 'dominios',
                'default_currency' => 'USD',
                'company' => 'phinxlab',
                'detection_keywords' => ['GoDaddy', 'Go Daddy'],
            ],
            [
                'name' => 'Sector Copier',
                'slug' => 'sector-copier',
                'category' => 'otro',
                'default_currency' => 'ARS',
                'company' => 'novatech',
                'detection_keywords' => ['Sector Copier', 'Sector Copier Srl', 'copias fijas', 'Impresoras'],
            ],
            [
                'name' => 'Telefónica',
                'slug' => 'telefonica',
                'category' => 'telefonia',
                'default_currency' => 'ARS',
                'company' => 'novatech',
                'detection_keywords' => ['Telefonica De Argentina', 'Telefónica', 'Movistar'],
            ],
            [
                'name' => 'Telecentro',
                'slug' => 'telecentro',
                'category' => 'internet',
                'default_currency' => 'ARS',
                'company' => 'novatech',
                'detection_keywords' => ['TELECENTRO', 'Telecentro'],
            ],
            [
                'name' => 'Figma',
                'slug' => 'figma',
                'category' => 'licencias',
                'default_currency' => 'USD',
                'company' => 'novatech',
                'detection_keywords' => ['FIGMA', 'Figma Inc'],
            ],
            [
                'name' => 'Freshworks',
                'slug' => 'freshworks',
                'category' => 'licencias',
                'default_currency' => 'USD',
                'company' => 'novatech',
                'detection_keywords' => ['Freshworks', 'Freshdesk'],
            ],
            [
                'name' => 'MENZE',
                'slug' => 'menze',
                'category' => 'licencias',
                'default_currency' => 'USD',
                'company' => 'novatech',
                'detection_keywords' => ['MENZE INC', 'MENZE', 'Mercado Libre'],
            ],
            [
                'name' => 'NIC Argentina',
                'slug' => 'nic',
                'category' => 'dominios',
                'default_currency' => 'ARS',
                'company' => 'novatech',
                'detection_keywords' => ['Fondo De Cooperacion Tecnica', 'NIC', 'nic.ar'],
            ],
            [
                'name' => 'Envato',
                'slug' => 'envato',
                'category' => 'licencias',
                'default_currency' => 'USD',
                'company' => 'novatech',
                'detection_keywords' => ['Envato Pty', 'Envato'],
            ],
            [
                'name' => 'DeskTime',
                'slug' => 'desktime',
                'category' => 'licencias',
                'default_currency' => 'USD',
                'company' => 'novatech',
                'detection_keywords' => ['DeskTime', 'Desktime'],
            ],
            [
                'name' => 'Bessel (UPS)',
                'slug' => 'bessel',
                'category' => 'otro',
                'default_currency' => 'ARS',
                'company' => 'novatech',
                'detection_keywords' => ['Bessel S.R.L', 'Bessel', 'UPS'],
            ],
        ];

        foreach ($providers as $provider) {
            InvoiceProvider::updateOrCreate(
                ['slug' => $provider['slug']],
                $provider
            );
        }
    }
}
