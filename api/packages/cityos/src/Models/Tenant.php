<?php

namespace Fleetbase\CityOS\Models;

use Fleetbase\Models\Model;
use Fleetbase\Models\Company;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Casts\Json;

class Tenant extends Model
{
    use HasUuid, HasPublicId, HasApiModelBehavior;

    protected $table = 'cityos_tenants';
    protected $payloadKey = 'tenant';
    protected $publicIdType = 'tenant';

    protected $fillable = [
        'uuid',
        'company_uuid',
        'country_uuid',
        'city_uuid',
        'sector_uuid',
        'category_uuid',
        'handle',
        'name',
        'name_ar',
        'type',
        'subscription_tier',
        'status',
        'domain',
        'subdomain',
        'medusa_tenant_id',
        'payload_tenant_id',
        'erpnext_company',
        'branding',
        'settings',
        'meta',
    ];

    protected $casts = [
        'branding' => Json::class,
        'settings' => Json::class,
        'meta' => Json::class,
    ];

    protected $searchableColumns = ['name', 'name_ar', 'handle'];

    protected $filterParams = [
        'company_uuid', 'country_uuid', 'city_uuid',
        'sector_uuid', 'category_uuid', 'status',
        'subscription_tier', 'type',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_uuid', 'uuid');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_uuid', 'uuid');
    }

    public function sector()
    {
        return $this->belongsTo(Sector::class, 'sector_uuid', 'uuid');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_uuid', 'uuid');
    }

    public function channels()
    {
        return $this->hasMany(Channel::class, 'tenant_uuid', 'uuid');
    }

    public function portals()
    {
        return $this->hasMany(Portal::class, 'tenant_uuid', 'uuid');
    }

    public function getNodeContext(): array
    {
        return [
            'country' => $this->country?->code ?? '',
            'cityOrTheme' => $this->city?->slug ?? '',
            'sector' => $this->sector?->slug ?? '',
            'category' => $this->category?->slug ?? '',
            'subcategory' => $this->category?->parent ? $this->category->slug : '',
            'tenant' => $this->handle ?? $this->uuid,
            'brand' => $this->branding['name'] ?? $this->name,
            'locale' => $this->country?->default_locale ?? 'ar-SA',
            'processingRegion' => $this->country?->processing_region ?? 'me-central-1',
            'residencyClass' => $this->country?->residency_class ?? 'sovereign',
        ];
    }
}
