<?php

namespace spec\Pim\Bundle\CatalogBundle\Doctrine\Common\Remover;

use Akeneo\Component\StorageUtils\Remover\RemovingOptionsResolverInterface;
use Doctrine\Common\Persistence\ObjectManager;
use PhpSpec\ObjectBehavior;
use Pim\Bundle\CatalogBundle\Event\CategoryEvents;
use Pim\Bundle\CatalogBundle\Model\CategoryInterface;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CategoryRemoverSpec extends ObjectBehavior
{
    function let(
        ObjectManager $objectManager,
        RemovingOptionsResolverInterface $optionsResolver,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->beConstructedWith($objectManager, $optionsResolver, $eventDispatcher);
    }

    function it_is_a_remover()
    {
        $this->shouldImplement('Akeneo\Component\StorageUtils\Remover\RemoverInterface');
    }

    function it_dispatches_an_event_when_removing_a_category(
        $eventDispatcher,
        $objectManager,
        CategoryInterface $category,
        ProductInterface $product1,
        ProductInterface $product2
    ) {
        $category->isRoot()->willReturn(false);
        $category->getProducts()->willReturn([$product1, $product2]);
        $product1->removeCategory($category)->shouldBeCalled();
        $product2->removeCategory($category)->shouldBeCalled();

        $eventDispatcher->dispatch(
            CategoryEvents::PRE_REMOVE_CATEGORY,
            Argument::type('Symfony\Component\EventDispatcher\GenericEvent')
        )->shouldBeCalled();

        $objectManager->remove($category)->shouldBeCalled();

        $this->remove($category, ['flush' => false]);
    }

    function it_dispatches_an_event_when_removing_a_tree(
        $eventDispatcher,
        $objectManager,
        CategoryInterface $tree
    ) {
        $tree->isRoot()->willReturn(true);
        $tree->getProducts()->willReturn([]);

        $eventDispatcher->dispatch(
            CategoryEvents::PRE_REMOVE_TREE,
            Argument::type('Symfony\Component\EventDispatcher\GenericEvent')
        )->shouldBeCalled();

        $objectManager->remove($tree)->shouldBeCalled();

        $this->remove($tree, ['flush' => false]);
    }

    function it_throws_exception_when_remove_anything_else_than_a_category()
    {
        $anythingElse = new \stdClass();
        $this
            ->shouldThrow(
                new \InvalidArgumentException(
                    sprintf(
                        'Expects a "Pim\Bundle\CatalogBundle\Model\CategoryInterface", "%s" provided.',
                        get_class($anythingElse)
                    )
                )
            )
            ->duringRemove($anythingElse);
    }
}
