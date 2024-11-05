<?php

namespace HeimrichHannot\ListWidgetBundle\Response;

use Ausi\SlugGenerator\SlugGenerator;
use Ausi\SlugGenerator\SlugOptions;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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

    public static function applySearchToCriteria(Criteria $criteria, Request $request, array $fields): void
    {
        $slugger = new SlugGenerator((new SlugOptions())->setValidChars('A-Za-z0-9'));
        if (!empty($request->query->get('search')['value'])) {
            foreach ($fields as $field) {
                $criteria->orWhere(Criteria::expr()->contains($field, $slugger->generate($request->query->get('search')['value'])));
            }
        }
    }

    public static function applyListConfigToCriteria(Criteria $criteria, Request $request, array $fields): void
    {
        $criteria->setMaxResults($request->query->get('length'));
        $criteria->setFirstResult($request->query->get('start', 0));

        if (!empty($request->query->get('order'))) {
            foreach ($request->query->get('order') as $order) {
                $criteria->orderBy([$fields[$order['column']] => $order['dir']]);
            }
        }
    }

    public static function adjustDataResultStructure(array &$data): void
    {
        array_walk($data, function (&$date) {
            $date = array_map(function ($value) {
                return ['value' => $value];
            }, array_values($date));
        });
    }
}