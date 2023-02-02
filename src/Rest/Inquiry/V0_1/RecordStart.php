<?php

declare(strict_types=1);

namespace RTCKit\Eqivo\Rest\Inquiry\V0_1;

use RTCKit\FiCore\Command\Channel\Record;
use RTCKit\Eqivo\Rest\Inquiry\AbstractInquiry;
use RTCKit\FiCore\Switch\{
    Channel,
    Core
};

/**
 * @OA\Schema(
 *     schema="RecordStartParameters",
 *     required={"ConferenceName"},
 * )
 */
class RecordStart extends AbstractInquiry
{
    /**
     * @OA\Property(
     *     description="Unique identifier of the call to be recorded",
     *     example="052d04e4-019a-45ff-a562-f74d4ae99ea2",
     * )
     */
    public string $CallUUID;

    /**
     * @OA\Property(
     *     description="File format (extension)",
     *     example="wav",
     *     enum=RTCKit\Eqivo\Rest\Controller\V0_1\RecordStart::RECORD_FILE_FORMATS,
     *     default=RTCKit\Eqivo\Rest\Controller\V0_1\RecordStart::DEFAULT_RECORD_FORMAT,
     * )
     */
    public string $FileFormat;

    /**
     * @OA\Property(
     *     description="Directory path/URI where the recording file will be saved",
     *     example="/tmp/recordings",
     *     default="",
     * )
     */
    public string $FilePath;

    /**
     * @OA\Property(
     *     description="Recording file name (without extension); if empty, a timestamp based file name will be generated",
     *     example="sample_recording",
     *     default="",
     * )
     */
    public string $FileName;

    /**
     * @OA\Property(
     *     description="Maximum recording length, in seconds",
     *     type="int",
     *     minimum=1,
     *     example=89,
     *     default=RTCKit\Eqivo\Rest\Controller\V0_1\RecordStart::DEFAULT_TIME_LIMIT,
     * )
     */
    public string|int $TimeLimit;

    public Channel $channel;

    public function export(): Record\Request
    {
        $request = new Record\Request();

        $request->action = Record\ActionEnum::Start;
        $request->channel = $this->channel;
        $request->medium = "{$this->FilePath}{$this->FileName}.{$this->FileFormat}";
        $request->duration = (float)$this->TimeLimit;

        return $request;
    }
}
