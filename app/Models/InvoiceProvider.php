<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceProvider extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'default_currency',
        'company',
        'detection_keywords',
        'custom_prompt',
        'is_active',
        'is_multi',
        'sync_to_odoo',
        'odoo_partner_id',
        'odoo_product_id',
        'odoo_tax_id',
        'odoo_tax_ids',
        'odoo_account_id',
        'odoo_document_type_id',
        'odoo_ref_source',
    ];

    protected $casts = [
        'detection_keywords' => 'array',
        'is_active' => 'boolean',
        'is_multi' => 'boolean',
        'sync_to_odoo' => 'boolean',
        'odoo_tax_ids' => 'array',
    ];

    /**
     * Busca un proveedor por slug.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Retorna todos los proveedores activos como opciones para select.
     */
    public static function getOptions(): array
    {
        return static::where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->toArray();
    }
}
