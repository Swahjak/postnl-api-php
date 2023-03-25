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

namespace Firstred\PostNL\Service\Adapter\Rest;

use Firstred\PostNL\Entity\ReasonNoTimeframe;
use Firstred\PostNL\Entity\Request\GetTimeframes;
use Firstred\PostNL\Entity\Response\ResponseTimeframes;
use Firstred\PostNL\Entity\Timeframe;
use Firstred\PostNL\Entity\TimeframeTimeFrame;
use Firstred\PostNL\Exception\DeserializationException;
use Firstred\PostNL\Exception\EntityNotFoundException;
use Firstred\PostNL\Exception\HttpClientException;
use Firstred\PostNL\Exception\InvalidArgumentException as PostNLInvalidArgumentException;
use Firstred\PostNL\Exception\NotSupportedException;
use Firstred\PostNL\Exception\ResponseException;
use Firstred\PostNL\Service\Adapter\TimeframeServiceAdapterInterface;
use Firstred\PostNL\Util\Util;
use Psr\Http\Message\RequestInterface;
use const PHP_QUERY_RFC3986;

/**
 * @since 2.0.0
 */
class TimeframeServiceRestAdapter extends AbstractRestAdapter implements TimeframeServiceAdapterInterface
{
    // Endpoints
    const LIVE_ENDPOINT = 'https://api.postnl.nl/shipment/${VERSION}/calculate/timeframes';
    const SANDBOX_ENDPOINT = 'https://api-sandbox.postnl.nl/shipment/${VERSION}/calculate/timeframes';

    /**
     * Build the GetTimeframes request for the REST API.
     *
     * @since 2.0.0
     */
    public function buildGetTimeframesRequest(GetTimeframes $getTimeframes): RequestInterface
    {
        $timeframe = $getTimeframes->getTimeframe()[0];
        $query = [
            'AllowSundaySorting' => in_array(needle: $timeframe->getSundaySorting(), haystack: [true, 'true', 1], strict: true) ? 'true' : 'false',
            'StartDate'          => $timeframe->getStartDate()->format(format: 'd-m-Y'),
            'EndDate'            => $timeframe->getEndDate()->format(format: 'd-m-Y'),
            'PostalCode'         => $timeframe->getPostalCode(),
            'HouseNumber'        => $timeframe->getHouseNr(),
            'CountryCode'        => $timeframe->getCountryCode(),
            'Options'            => '',
        ];
        if ($interval = $timeframe->getInterval()) {
            $query['Interval'] = $interval;
        }
        if ($houseNrExt = $timeframe->getHouseNrExt()) {
            $query['HouseNrExt'] = $houseNrExt;
        }
        if ($timeframeRange = $timeframe->getTimeframeRange()) {
            $query['TimeframeRange'] = $timeframeRange;
        }
        if ($street = $timeframe->getStreet()) {
            $query['Street'] = $street;
        }
        if ($city = $timeframe->getCity()) {
            $query['City'] = $city;
        }
        foreach ($timeframe->getOptions() as $option) {
            $query['Options'] .= ",$option";
        }
        $query['Options'] = ltrim(string: $query['Options'], characters: ',');
        $query['Options'] = $query['Options'] ?: 'Daytime';

        $endpoint = '?'.http_build_query(data: $query, numeric_prefix: '', arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);

        return $this->getRequestFactory()->createRequest(
            method: 'GET',
            uri: Util::versionStringToURLString(
                version: $this->getVersion(),
                url: (($this->isSandbox() ? static::SANDBOX_ENDPOINT : static::LIVE_ENDPOINT).$endpoint),
            ))
            ->withHeader('apikey', value: $this->getApiKey()->getString())
            ->withHeader('Accept', value: 'application/json');
    }

    /**
     * Process GetTimeframes Response REST.
     *
     * @throws HttpClientException
     * @throws NotSupportedException
     * @throws PostNLInvalidArgumentException
     * @throws ResponseException
     * @throws DeserializationException
     * @throws EntityNotFoundException
     * @since 2.0.0
     */
    public function processGetTimeframesResponse(mixed $response): ?ResponseTimeframes
    {
        $body = json_decode(json: static::getResponseText(response: $response));
        // Standardize the object here
        if (isset($body->ReasonNoTimeframes)) {
            if (!isset($body->ReasonNoTimeframes->ReasonNoTimeframe)) {
                $body->ReasonNoTimeframes->ReasonNoTimeframe = [];
            }

            if (!is_array(value: $body->ReasonNoTimeframes->ReasonNoTimeframe)) {
                $body->ReasonNoTimeframes->ReasonNoTimeframe = [$body->ReasonNoTimeframes->ReasonNoTimeframe];
            }

            $newNotimeframes = [];
            foreach ($body->ReasonNoTimeframes->ReasonNoTimeframe as $reasonNotimeframe) {
                $newNotimeframes[] = ReasonNoTimeframe::jsonDeserialize(json: (object) ['ReasonNoTimeframe' => $reasonNotimeframe]);
            }

            $body->ReasonNoTimeframes = $newNotimeframes;
        } else {
            $body->ReasonNoTimeframes = [];
        }

        if (isset($body->Timeframes)) {
            if (!isset($body->Timeframes->Timeframe)) {
                $body->Timeframes->Timeframe = [];
            }

            if (!is_array(value: $body->Timeframes->Timeframe)) {
                $body->Timeframes->Timeframe = [$body->Timeframes->Timeframe];
            }

            $newTimeframes = [];
            foreach ($body->Timeframes->Timeframe as $timeframe) {
                $newTimeframeTimeframe = [];
                if (!is_array(value: $timeframe->Timeframes->TimeframeTimeFrame)) {
                    $timeframe->Timeframes->TimeframeTimeFrame = [$timeframe->Timeframes->TimeframeTimeFrame];
                }
                foreach ($timeframe->Timeframes->TimeframeTimeFrame as $timeframetimeframe) {
                    $newTimeframeTimeframe[] = TimeframeTimeFrame::jsonDeserialize(
                        json: (object) ['TimeframeTimeFrame' => $timeframetimeframe]
                    );
                }
                $timeframe->Timeframes = $newTimeframeTimeframe;

                $newTimeframes[] = Timeframe::jsonDeserialize(json: (object) ['Timeframe' => $timeframe]);
            }
            $body->Timeframes = $newTimeframes;
        } else {
            $body->Timeframes = [];
        }

        $object = ResponseTimeframes::create();
        $object->setReasonNoTimeframes(ReasonNoTimeframes: $body->ReasonNoTimeframes);
        $object->setTimeframes(Timeframes: $body->Timeframes);

        return $object;
    }
}
