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

namespace Firstred\PostNL\Entity\Request;

use Firstred\PostNL\Attribute\SerializableProperty;
use Firstred\PostNL\Entity\AbstractEntity;
use Firstred\PostNL\Entity\Customer;
use Firstred\PostNL\Entity\Message\LabellingMessage;
use Firstred\PostNL\Entity\Shipment;
use Firstred\PostNL\Enum\SoapNamespace;
use Sabre\Xml\Writer;

/**
 * @since 1.0.0
 */
class GenerateLabel extends AbstractEntity
{
    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?Customer $Customer = null;

    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?LabellingMessage $Message = null;

    /** @var Shipment[]|null */
    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?array $Shipments = null;

    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?string $LabelSignature;

    public function __construct(
        /** @param $Shipments Shipment[]|null */
        ?array            $Shipments = null,
        ?LabellingMessage $Message = null,
        ?Customer         $Customer = null,
        ?string           $LabelSignature = null
    ) {
        parent::__construct();

        $this->setShipments(Shipments: $Shipments);
        $this->setMessage(Message: $Message ?: new LabellingMessage());
        $this->setCustomer(Customer: $Customer);
        if ($LabelSignature) {
            $this->setLabelSignature(LabelSignature: $LabelSignature);
        }
    }

    public function getCustomer(): ?Customer
    {
        return $this->Customer;
    }

    public function setCustomer(?Customer $Customer): static
    {
        $this->Customer = $Customer;

        return $this;
    }

    public function getMessage(): ?LabellingMessage
    {
        return $this->Message;
    }

    public function setMessage(?LabellingMessage $Message): static
    {
        $this->Message = $Message;

        return $this;
    }

    /**
     * @return Shipment[]|null
     */
    public function getShipments(): ?array
    {
        return $this->Shipments;
    }

    /**
     * @param Shipment[]|null $Shipments
     * @return static
     */
    public function setShipments(?array $Shipments): static
    {
        $this->Shipments = $Shipments;

        return $this;
    }

    public function getLabelSignature(): ?string
    {
        return $this->LabelSignature;
    }

    public function setLabelSignature(?string $LabelSignature): static
    {
        $this->LabelSignature = $LabelSignature;

        return $this;
    }

    public function jsonSerialize(): array
    {
        $json = [];
        if (!$this->currentService || !in_array(needle: $this->currentService, haystack: array_keys(array: static::$defaultProperties))) {
            return $json;
        }

        foreach (array_keys(array: static::$defaultProperties[$this->currentService]) as $propertyName) {
            if (isset($this->$propertyName)) {
                // The REST API only seems to accept one shipment per request at the moment of writing (Sep. 24th, 2017)
                if ('Shipments' === $propertyName && count(value: $this->$propertyName) >= 1) {
                    $json[$propertyName] = $this->{$propertyName}[0];
                } else {
                    $json[$propertyName] = $this->$propertyName;
                }
            }
        }

        return $json;
    }

    public function xmlSerialize(Writer $writer): void
    {
        $xml = [];
        if (!$this->currentService || !in_array(needle: $this->currentService, haystack: array_keys(array: static::$defaultProperties))) {
            $writer->write(value: $xml);

            return;
        }

        foreach (static::$defaultProperties[$this->currentService] as $propertyName => $namespace) {
            if ('Shipments' === $propertyName) {
                $shipments = [];
                foreach ($this->Shipments as $shipment) {
                    $shipments[] = ["{{$namespace}}Shipment" => $shipment];
                }
                $xml["{{$namespace}}Shipments"] = $shipments;
            } elseif (isset($this->$propertyName)) {
                $xml[$namespace ? "{{$namespace}}{$propertyName}" : $propertyName] = $this->$propertyName;
            }
        }
        // Auto extending this object with other properties is not supported with SOAP
        $writer->write(value: $xml);
    }
}
