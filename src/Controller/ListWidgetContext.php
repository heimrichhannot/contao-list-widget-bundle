<?php

namespace HeimrichHannot\ListWidgetBundle\Controller;

use Ausi\SlugGenerator\SlugGenerator;
use Ausi\SlugGenerator\SlugOptions;
use Contao\Controller;
use Doctrine\Common\Collections\Criteria;
use HeimrichHannot\ListWidgetBundle\Widget\ListWidget;
use Symfony\Component\HttpFoundation\Request;

class ListWidgetContext
{
    public function __construct(
        public readonly string $table,
        public readonly string $field,
        public readonly string $id,
        public readonly array $fieldConfig,
        public readonly Request $request,
    ) {
    }

    public static function createFromRequest(Request $request): ListWidgetContext
    {
        $table = $request->query->get('table');
        $field = $request->query->get('scope');

        if (!$table || !$field) {
            throw new \Exception('Missing required parameters.');
        }

        Controller::loadDataContainer($table);

        $fieldConfig = $GLOBALS['TL_DCA'][$table]['fields'][$field] ?? false;

        if (!$fieldConfig) {
            throw new \Exception('Invalid field type.');
        }

        if (ListWidget::TYPE !== $fieldConfig['inputType']) {
            throw new \Exception('Invalid field type.');
        }

        $id = $request->query->get('id');

        return new ListWidgetContext($table, $field, $id, $fieldConfig, $request);
    }

    public function applySearchToCriteria(Criteria $criteria, array $fields): void
    {
        $slugger = new SlugGenerator((new SlugOptions())->setValidChars('A-Za-z0-9'));
        if (!empty($this->request->query->get('search')['value'])) {
            foreach ($fields as $field) {
                $criteria->orWhere(Criteria::expr()->contains(
                    $field,
                    $slugger->generate($this->request->query->get('search')['value'])
                ));
            }
        }
    }

    public function applyListConfigToCriteria(Criteria $criteria, array $fields): void
    {
        $criteria->setMaxResults($this->request->query->get('length'));
        $criteria->setFirstResult($this->request->query->get('start', 0));

        if (!empty($this->request->query->get('order'))) {
            foreach ($this->request->query->get('order') as $order) {
                $criteria->orderBy([
                    $fields[$order['column']] => $order['dir'],
                ]);
            }
        }
    }

    public function adjustDataResultStructure(array &$data): void
    {
        array_walk($data, function (&$date) {
            $date = array_map(fn($value) => [
                'value' => $value,
            ], array_values($date));
        });
    }

    public function createResponse(int $countTotal, int $countFiltered, array $data): ListWidgetResponse
    {
        $this->adjustDataResultStructure($data);

        return new ListWidgetResponse($this->request->query->get('draw'), $countTotal, $countFiltered, $data);
    }
}