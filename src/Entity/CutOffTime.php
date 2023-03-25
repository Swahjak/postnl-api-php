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
use Sabre\Xml\Writer;

/**
 * @since 1.0.0
 */
class CutOffTime extends AbstractEntity
{
    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?string $Day = null;

    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?string $Time = null;

    #[SerializableProperty(namespace: SoapNamespace::Domain)]
    protected ?bool $Available = null;

    public function __construct(?string $Day = null, ?string $Time = null, ?bool $Available = null)
    {
        parent::__construct();

        $this->setDay(Day: $Day);
        $this->setTime(Time: $Time);
        $this->setAvailable(Available: $Available);
    }

    public function getDay(): ?string
    {
        return $this->Day;
    }

    public function setDay(?string $Day): static
    {
        $this->Day = $Day;

        return $this;
    }

    public function getTime(): ?string
    {
        return $this->Time;
    }

    public function setTime(?string $Time): static
    {
        $this->Time = $Time;

        return $this;
    }

    public function getAvailable(): ?bool
    {
        return $this->Available;
    }

    public function setAvailable(?bool $Available): static
    {
        $this->Available = $Available;

        return $this;
    }

    public function xmlSerialize(Writer $writer): void
    {
        $xml = [];
        if (!$this->currentService || !in_array(needle: $this->currentService, haystack: array_keys(array: static::$defaultProperties))) {
            $writer->write(value: $xml);

            return;
        }

        foreach (static::$defaultProperties[$this->currentService] as $propertyName => $namespace) {
            if (isset($this->$propertyName)) {
                if ('Available' === $propertyName) {
                    if (is_bool(value: $this->$propertyName)) {
                        $xml[$namespace ? "{{$namespace}}{$propertyName}" : $propertyName] = $this->$propertyName ? 'true' : 'false';
                    } elseif (is_int(value: $this->$propertyName)) {
                        $xml[$namespace ? "{{$namespace}}{$propertyName}" : $propertyName] = 1 === $this->$propertyName ? 'true' : 'false';
                    } else {
                        $xml[$namespace ? "{{$namespace}}{$propertyName}" : $propertyName] = $this->$propertyName;
                    }
                } else {
                    $xml[$namespace ? "{{$namespace}}{$propertyName}" : $propertyName] = $this->$propertyName;
                }
            }
        }
        // Auto extending this object with other properties is not supported with SOAP
        $writer->write(value: $xml);
    }
}
