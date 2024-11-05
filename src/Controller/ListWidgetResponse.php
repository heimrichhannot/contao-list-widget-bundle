<?php

namespace HeimrichHannot\ListWidgetBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class ListWidgetResponse extends JsonResponse
{
    public function __construct(int $draw, int $recordsTotal, int $recordsFiltered, array $data = null, ?string $error = null, int $status = 200, array $headers = [], bool $json = false)
    {
        $data = [
            'result' => [
                'data' => [
                    'draw' => $draw,
                    'recordsTotal' => $recordsTotal,
                    'recordsFiltered' => $recordsFiltered,
                    'data' => $data,
                ],
            ]
        ];

        if ($error) {
            $data['result']['error'] = $error;
        }

        parent::__construct($data, $status, $headers, $json);
    }
}