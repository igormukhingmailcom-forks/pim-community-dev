<?php

namespace Pim\Bundle\DataGridBundle\Extension\MassAction;

use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datagrid\ManagerInterface;
use Oro\Bundle\DataGridBundle\Datagrid\RequestParameters;
use Oro\Bundle\DataGridBundle\Extension\ExtensionVisitorInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionExtension;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionParametersParser;
use Pim\Bundle\DataGridBundle\Extension\Filter\FilterExtension;
use Pim\Bundle\DataGridBundle\Extension\MassAction\Handler\MassActionHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Mass action dispatcher
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MassActionDispatcher
{
    /** @var MassActionHandlerRegistry $handlerRegistry */
    protected $handlerRegistry;

    /** @var Manager $manager */
    protected $manager;

    /** @var RequestParameters $requestParams */
    protected $requestParams;

    /** @var MassActionParametersParser $parametersParser */
    protected $parametersParser;

    /**
     * Constructor
     *
     * @param MassActionHandlerRegistry  $handlerRegistry
     * @param ManagerInterface           $manager
     * @param RequestParameters          $requestParams
     * @param MassActionParametersParser $parametersParser
     */
    public function __construct(
        MassActionHandlerRegistry $handlerRegistry,
        ManagerInterface $manager,
        RequestParameters $requestParams,
        MassActionParametersParser $parametersParser
    ) {
        $this->handlerRegistry  = $handlerRegistry;
        $this->manager          = $manager;
        $this->requestParams    = $requestParams;
        $this->parametersParser = $parametersParser;
    }

    /**
     * Dispatch datagrid mass action
     *
     * @param Request $request
     *
     * @throws \LogicException
     *
     * @return MassActionResponseInterface
     */
    public function dispatch(Request $request)
    {
        $parameters = $this->parametersParser->parse($request);
        $inset   = $this->prepareInsetParameter($parameters);
        $values  = $this->prepareValuesParameter($parameters);
        $filters = $this->prepareFiltersParameter($parameters);

        $actionName = $request->get('actionName');
        if ($inset && empty($values)) {
            throw new \LogicException(sprintf('There is nothing to do in mass action "%s"', $actionName));
        }

        $datagridName = $request->get('gridName');
        $datagrid   = $this->manager->getDatagrid($datagridName);
        $massAction = $this->getMassActionByName($actionName, $datagrid);
        $this->requestParams->set(FilterExtension::FILTER_ROOT_PARAM, $filters);

        return $this->performMassAction($datagrid, $massAction, $inset, $values);
    }

    /**
     * @param array $parameters
     *
     * @return bool
     */
    protected function prepareInsetParameter(array $parameters)
    {
        return isset($parameters['inset']) ? $parameters['inset'] : true;
    }

    /**
     * @param array $parameters
     *
     * @return array
     */
    protected function prepareValuesParameter(array $parameters)
    {
        return isset($parameters['values']) ? $parameters['values'] : [];
    }

    /**
     * @param array $parameters
     *
     * @return array
     */
    protected function prepareFiltersParameter(array $parameters)
    {
        return isset($parameters['filters']) ? $parameters['filters'] : [];
    }

    /**
     * Prepare query builder, apply mass action parameters and call handler
     *
     * @param DatagridInterface   $datagrid
     * @param MassActionInterface $massAction
     * @param bool                $inset
     * @param string              $values
     *
     * @return MassActionResponseInterface
     */
    protected function performMassAction(
        DatagridInterface $datagrid,
        MassActionInterface $massAction,
        $inset,
        $values
    ) {
        $qb = $datagrid->getAcceptedDatasource()->getQueryBuilder();

        $repository = $datagrid->getDatasource()->getMassActionRepository();
        $repository->applyMassActionParameters($qb, $inset, $values);

        $handler = $this->getMassActionHandler($massAction);

        return $handler->handle($datagrid, $massAction);
    }

    /**
     * @param string            $massActionName
     * @param DatagridInterface $datagrid
     *
     * @throws \LogicException
     *
     * @return \Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface
     */
    protected function getMassActionByName($massActionName, DatagridInterface $datagrid)
    {
        $massAction = null;
        $extensions = array_filter(
            $datagrid->getAcceptor()->getExtensions(),
            function (ExtensionVisitorInterface $extension) {
                return $extension instanceof MassActionExtension;
            }
        );

        /** @var MassActionExtension|bool $extension */
        $extension = reset($extensions);
        if ($extension === false) {
            throw new \LogicException("MassAction extension is not applied to datagrid.");
        }

        $massAction = $extension->getMassAction($massActionName, $datagrid);

        if (!$massAction) {
            throw new \LogicException(sprintf('Can\'t find mass action "%s"', $massActionName));
        }

        return $massAction;
    }

    /**
     * Get mass action handler from handler registry
     *
     * @param MassActionInterface $massAction
     *
     * @return MassActionHandlerInterface
     */
    protected function getMassActionHandler(MassActionInterface $massAction)
    {
        $handlerAlias = $massAction->getOptions()->offsetGet('handler');
        $handler      = $this->handlerRegistry->getHandler($handlerAlias);

        return $handler;
    }

    /**
     * Get mass action from mass action and datagrid names
     *
     * @param string $actionName
     * @param string $datagridName
     *
     * @return \Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface
     *
     * TODO: Need some clean up and optimization
     */
    public function getMassActionByNames($actionName, $datagridName)
    {
        $datagrid = $this->manager->getDatagrid($datagridName);

        return $this->getMassActionByName($actionName, $datagrid);
    }
}
