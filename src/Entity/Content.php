<?php
declare(strict_types=1);
/**
 * The MIT License (MIT).
 *
 * Copyright (c) 2017-2023 Michael Dekker (https://github.com/firstred)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
 * associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute,
 * sublicense, and/or sell copies of the Software, and to permit persons to whom the Software
 * is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or
 * substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT
 * NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author    Michael Dekker <git@michaeldekker.nl>
 * @copyright 2017-2023 Michael Dekker
 * @license   https://opensource.org/licenses/MIT The MIT License
 */

namespace Firstred\PostNL\Entity;

use Firstred\PostNL\Attribute\SerializableProperty;
use Firstred\PostNL\Enum\SoapNamespace;

/**
 * @since 1.0.0
 */
class Content extends AbstractEntity
{
    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?string $CountryOfOrigin = null;

    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?string $Description = null;

    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?string $HSTariffNr = null;

    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?string $Quantity = null;

    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?string $Value = null;

    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?string $Weight = null;

    /** @var Content[]|null */
    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?array $Content = null;

    public function __construct(
        ?string $CountryOfOrigin = null,
        ?string $Description = null,
        ?string $HSTariffNr = null,
        ?string $Quantity = null,
        ?string $Value = null,
        ?string $Weight = null,
        ?array  $Content = null
    ) {
        parent::__construct();

        $this->setCountryOfOrigin(CountryOfOrigin: $CountryOfOrigin);
        $this->setDescription(Description: $Description);
        $this->setHSTariffNr(HSTariffNr: $HSTariffNr);
        $this->setQuantity(Quantity: $Quantity);
        $this->setValue(Value: $Value);
        $this->setWeight(Weight: $Weight);
        $this->setContent(Content: $Content);
    }

    public function getCountryOfOrigin(): ?string
    {
        return $this->CountryOfOrigin;
    }

    public function setCountryOfOrigin(?string $CountryOfOrigin): static
    {
        $this->CountryOfOrigin = $CountryOfOrigin;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->Description;
    }

    public function setDescription(?string $Description): static
    {
        $this->Description = $Description;

        return $this;
    }

    public function getHSTariffNr(): ?string
    {
        return $this->HSTariffNr;
    }

    public function setHSTariffNr(?string $HSTariffNr): static
    {
        $this->HSTariffNr = $HSTariffNr;

        return $this;
    }

    public function getQuantity(): ?string
    {
        return $this->Quantity;
    }

    public function setQuantity(?string $Quantity): static
    {
        $this->Quantity = $Quantity;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->Value;
    }

    public function setValue(?string $Value): static
    {
        $this->Value = $Value;

        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->Weight;
    }

    public function setWeight(?string $Weight): static
    {
        $this->Weight = $Weight;

        return $this;
    }

    public function getContent(): ?array
    {
        return $this->Content;
    }

    public function setContent(?array $Content): static
    {
        $this->Content = $Content;

        return $this;
    }
}
