<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ChirpsClient
{
    private const BASE_URL = 'https://climateserv.servirglobal.net/chirps';

    public function submit(string $geometry, string $begin, string $end): string
    {
        $response = Http::connectTimeout(10)->timeout(60)->get(self::BASE_URL.'/submitDataRequest/', [
            'datatype' => 0,
            'begintime' => date('m/d/Y', strtotime($begin)),
            'endtime' => date('m/d/Y', strtotime($end)),
            'intervaltype' => 0,
            'operationtype' => 5,
            'geometry' => $geometry,
            'isZip_CurrentDataType' => 'false',
        ])->throw()->json();

        $jobId = is_array($response) ? ($response[0] ?? null) : $response;
        if (! is_string($jobId) || $jobId === '') {
            throw new \RuntimeException('ClimateSERV did not return a CHIRPS job ID.');
        }

        return $jobId;
    }

    public function progress(string $jobId): float
    {
        return (float) Http::connectTimeout(10)->timeout(30)
            ->get(self::BASE_URL.'/getDataRequestProgress/', ['id' => $jobId])
            ->throw()->body();
    }

    public function data(string $jobId): array
    {
        $data = Http::connectTimeout(10)->timeout(60)
            ->get(self::BASE_URL.'/getDataFromRequest/', ['id' => $jobId])
            ->throw()->json('data');

        return is_array($data) ? $data : [];
    }
}
