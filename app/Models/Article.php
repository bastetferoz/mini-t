<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'category',
        'subcategory',
        'tags',
        'body',
        'status',
        'author_id',
        'attachments',
    ];

    protected $casts = [
        'tags' => 'array',
        'attachments' => 'array',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected static function booted(): void
    {
        static::creating(function ($article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }
            if (empty($article->author_id)) {
                $article->author_id = auth()->id();
            }
        });
    }

    public static function getCategoryOptions(): array
    {
        return [
            'infraestructura' => 'Infraestructura',
            'redes' => 'Redes',
            'servidores' => 'Servidores',
            'seguridad' => 'Seguridad',
            'procedimientos' => 'Procedimientos',
            'finops' => 'Facturación / FinOps',
            'otro' => 'Otros',
        ];
    }

    public static function getSubcategoryOptions(): array
    {
        return [
            // Infraestructura
            'vmware' => 'VMware',
            'hyper-v' => 'Hyper-V',
            'docker' => 'Docker',
            'kubernetes' => 'Kubernetes',
            'proxmox' => 'Proxmox',
            'aws' => 'AWS',
            // Redes
            'cisco' => 'Cisco',
            'mikrotik' => 'Mikrotik',
            'fortigate' => 'Fortigate',
            'vpn' => 'VPN',
            // Servidores
            'windows-server' => 'Windows Server',
            'linux' => 'Linux',
            'active-directory' => 'Active Directory',
            'dns' => 'DNS',
            // Seguridad
            'malware' => 'Malware',
            'ioc' => 'IOC',
            'ransomware' => 'Ransomware',
            'hardening' => 'Hardening',
            'incidentes' => 'Incidentes',
            // Procedimientos
            'alta-usuario' => 'Alta de usuario',
            'baja-empleado' => 'Baja de empleado',
            'cambio-notebook' => 'Cambio de notebook',
            'ssl' => 'Renovación SSL',
            'backup' => 'Backup',
            // FinOps
            'google-workspace' => 'Google Workspace',
            'azure' => 'Azure',
            'adobe' => 'Adobe',
        ];
    }
}
