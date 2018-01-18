<?php

namespace Concerto\PanelBundle\Controller;

use Concerto\PanelBundle\Service\FileService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Concerto\PanelBundle\DAO\DAOUnsupportedOperationException;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Concerto\PanelBundle\Service\DataTableService;
use Concerto\PanelBundle\Service\ImportService;
use Concerto\PanelBundle\Service\ExportService;
use Concerto\PanelBundle\Service\UserService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * @Security("has_role('ROLE_TABLE') or has_role('ROLE_SUPER_ADMIN')")
 */
class DataTableController extends AExportableTabController
{

    const ENTITY_NAME = "DataTable";
    const EXPORT_FILE_PREFIX = "DataTable_";

    private static $stream_param_data_collection_action_table_id;
    private static $stream_param_data_collection_action_prefixed;
    private $userService;

    public function __construct($environment, EngineInterface $templating, DataTableService $service, TranslatorInterface $translator, TokenStorage $securityTokenStorage, ImportService $importService, ExportService $exportService, UserService $userService, FileService $fileService)
    {
        parent::__construct($environment, $templating, $service, $translator, $securityTokenStorage, $importService, $exportService, $fileService);

        $this->entityName = self::ENTITY_NAME;
        $this->exportFilePrefix = self::EXPORT_FILE_PREFIX;

        $this->userService = $userService;
    }

    public function saveAction(Request $request, $object_id)
    {
        $result = $this->service->save(
            $this->securityTokenStorage->getToken()->getUser(), //
            $object_id, //
            $request->get("name"), //
            $request->get("description"), //
            $request->get("accessibility"), //
            $request->get("archived") === "1", //
            $this->userService->get($request->get("owner")), //
            $request->get("groups") //
        );
        return $this->getSaveResponse($result);
    }

    public function columnsCollectionAction($table_id)
    {
        $result_data = $this->service->getColumns($table_id);

        $response = new Response(json_encode($result_data));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function fetchColumnAction($table_id, $column_name)
    {
        $response = new Response(json_encode($this->service->getColumn($table_id, $column_name)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    protected function getStreamingDataCollectionResponse($table_id, $prefixed = 0)
    {
        set_time_limit(0);
        self::$stream_param_data_collection_action_prefixed = $prefixed;
        self::$stream_param_data_collection_action_table_id = $table_id;
        $response = new StreamedResponse();
        $response->setCallback(function () {
            $this->service->streamJsonData(DataTableController::$stream_param_data_collection_action_table_id, DataTableController::$stream_param_data_collection_action_prefixed == 1);
        });
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function dataCollectionAction(Request $request, $table_id, $prefixed = 0)
    {
        $filters = $request->get("filters");

        $result_data = array(
            'content' => $this->service->getFilteredData($table_id, $prefixed, $filters),
            'count' => $this->service->countFilteredData($table_id, $filters)
        );

        $response = new Response(json_encode($result_data));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function dataSectionAction($table_id)
    {
        return $this->templating->renderResponse("ConcertoPanelBundle:DataTable:data_section.html.twig", array(
            "table" => $this->service->get($table_id),
            "columns" => $this->service->getColumns($table_id)
        ));
    }

    public function deleteColumnAction($table_id, $column_names)
    {
        $this->service->deleteColumns($table_id, $column_names);
        $response = new Response(json_encode(array("result" => 0)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function deleteRowAction($table_id, $row_ids)
    {
        $this->service->deleteRows($table_id, $row_ids);
        $response = new Response(json_encode(array("result" => 0)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function truncateAction($table_id)
    {
        $this->service->truncate($table_id);
        $response = new Response(json_encode(array("result" => 0)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function saveColumnAction(Request $request, $table_id, $column_name)
    {
        try {
            $errors = $this->service->saveColumn($table_id, $column_name, $request->get("name"), $request->get("type"));
            if (count($errors) > 0) {
                for ($i = 0; $i < count($errors); $i++) {
                    $errors[$i] = $this->translator->trans($errors[$i]);
                }
                $response = new Response(json_encode(array("result" => 1, "errors" => $errors)));
            } else {
                $response = new Response(json_encode(array("result" => 0)));
            }
        } catch (DAOUnsupportedOperationException $exc) {
            $response = new Response(json_encode(array("result" => 2, "errors" => array($this->translator->trans('errors.table.column.conversion')))));
        }
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function insertRowAction($table_id)
    {
        $this->service->insertRow($table_id);
        $response = new Response(json_encode(array("result" => 0)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function updateRowAction(Request $request, $table_id, $row_id, $prefixed = 0)
    {
        $this->service->updateRow($table_id, $row_id, $request->get("values"), $prefixed == 1);
        $response = new Response(json_encode(array("result" => 0)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function importCsvAction(Request $request, $table_id, $restructure, $header, $delimiter, $enclosure)
    {
        try {
            $this->service->importFromCsv(
                $table_id,
                $this->fileService->getPrivateUploadDirectory() . $request->get("file"),
                $restructure === "1",
                $header === "1",
                $delimiter,
                $enclosure
            );
        } catch (\Exception $ex) {
            $response = new Response(json_encode(array("result" => 1, "errors" => array($ex->getMessage()))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
        $response = new Response(json_encode(array("result" => 0)));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

}
