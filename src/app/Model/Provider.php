<?php

namespace App\Model;

use App\Enum\ProviderEnum;

class Provider
{
    protected bool $authenticated = false;

    public function __construct(protected ProviderEnum $provider, protected array $conf)
    {
    }

    public function getId(): string
    {
        return $this->provider->value;
    }

    public function getLabel(): string
    {
        return $this->provider->label();
    }

    public function isEnabled(): bool
    {
        return $this->conf['enabled'];
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function setAuthenticated(bool $authenticated): void
    {
        $this->authenticated = $authenticated;
    }

    public function isEditable()
    {
        return $this->conf['editable'] ?? false;
    }
}
