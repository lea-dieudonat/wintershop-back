<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RefundRequestCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Demande de remboursement')
            ->setEntityLabelInPlural('Demandes de remboursement')
            ->setDefaultSort(['refundRequestedAt' => 'DESC'])
            ->setPageTitle('index', 'Demandes de remboursement en attente')
            ->setPageTitle('detail', fn(Order $order) => sprintf('Demande #%d', $order->getId()));
    }

    public function configureActions(Actions $actions): Actions
    {
        $approveRefund = Action::new('approveRefund', 'Accepter', 'fa fa-check')
            ->linkToCrudAction('approveRefund')
            ->setCssClass('btn btn-success')
            ->displayIf(fn(Order $order) => $order->getStatus() === OrderStatus::REFUND_REQUESTED);

        $rejectRefund = Action::new('rejectRefund', 'Refuser', 'fa fa-times')
            ->linkToCrudAction('rejectRefund')
            ->setCssClass('btn btn-danger')
            ->displayIf(fn(Order $order) => $order->getStatus() === OrderStatus::REFUND_REQUESTED);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $approveRefund)
            ->add(Crud::PAGE_DETAIL, $rejectRefund)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnIndex();

        yield AssociationField::new('user', 'Client')
            ->formatValue(function ($value, Order $order) {
                return sprintf(
                    '%s %s (%s)',
                    $order->getUser()->getFirstName(),
                    $order->getUser()->getLastName(),
                    $order->getUser()->getEmail()
                );
            });

        yield MoneyField::new('totalAmount', 'Montant')
            ->setCurrency('EUR');

        yield TextareaField::new('refundReason', 'Raison du remboursement')
            ->onlyOnDetail();

        yield DateTimeField::new('refundRequestedAt', 'Date de demande')
            ->setFormat('dd/MM/yyyy HH:mm');

        yield DateTimeField::new('deliveredAt', 'Date de livraison')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->onlyOnDetail();

        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'Pending' => OrderStatus::PENDING->value,
                'Paid' => OrderStatus::PAID->value,
                'Processing' => OrderStatus::PROCESSING->value,
                'Shipped' => OrderStatus::SHIPPED->value,
                'Delivered' => OrderStatus::DELIVERED->value,
                'Cancelled' => OrderStatus::CANCELLED->value,
                'Refund Requested' => OrderStatus::REFUND_REQUESTED->value,
                'Refunded' => OrderStatus::REFUNDED->value,
            ])
            ->renderAsBadges([
                OrderStatus::PENDING->value => 'warning',
                OrderStatus::PAID->value => 'success',
                OrderStatus::PROCESSING->value => 'primary',
                OrderStatus::SHIPPED->value => 'primary',
                OrderStatus::DELIVERED->value => 'success',
                OrderStatus::CANCELLED->value => 'danger',
                OrderStatus::REFUND_REQUESTED->value => 'warning',
                OrderStatus::REFUNDED->value => 'info',
            ])
            ->onlyOnDetail();
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Filtrer uniquement les demandes en attente
        $queryBuilder
            ->andWhere('entity.status = :status')
            ->setParameter('status', OrderStatus::REFUND_REQUESTED);

        return $queryBuilder;
    }

    public function approveRefund(AdminContext $context): RedirectResponse
    {
        // Récupérer l'ID depuis la requête
        $orderId = $context->getRequest()->query->get('entityId');

        if (!$orderId) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('admin');
        }

        // Récupérer l'entité
        $entityManager = $this->container->get('doctrine')->getManager();
        $order = $entityManager->getRepository(Order::class)->find((int) $orderId);

        if (!$order || !$order instanceof Order) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('admin');
        }

        if ($order->getStatus() !== OrderStatus::REFUND_REQUESTED) {
            $this->addFlash('error', 'Cette commande n\'est pas en attente de remboursement.');
            return $this->redirectToRoute('admin');
        }

        // Accepter le remboursement
        $order->setStatus(OrderStatus::REFUNDED);
        $order->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->flush();

        $this->addFlash('success', sprintf('Le remboursement de la commande #%d a été accepté.', $order->getId()));

        // Rediriger vers la liste des demandes
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function rejectRefund(AdminContext $context): RedirectResponse
    {
        // Récupérer l'ID depuis la requête
        $orderId = $context->getRequest()->query->get('entityId');

        if (!$orderId) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('admin');
        }

        // Récupérer l'entité
        $entityManager = $this->container->get('doctrine')->getManager();
        $order = $entityManager->getRepository(Order::class)->find((int) $orderId);

        if (!$order || !$order instanceof Order) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('admin');
        }

        if ($order->getStatus() !== OrderStatus::REFUND_REQUESTED) {
            $this->addFlash('error', 'Cette commande n\'est pas en attente de remboursement.');
            return $this->redirectToRoute('admin');
        }

        // Refuser le remboursement : retour à DELIVERED
        $order->setStatus(OrderStatus::DELIVERED);
        $order->setUpdatedAt(new \DateTimeImmutable());
        // On garde la raison et la date de demande pour l'historique

        $entityManager->flush();

        $this->addFlash('warning', sprintf('Le remboursement de la commande #%d a été refusé.', $order->getId()));

        // Rediriger vers la liste des demandes
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }
}
