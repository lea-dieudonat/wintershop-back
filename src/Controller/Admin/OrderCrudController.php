<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\OrderStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use App\Form\OrderItemType;

class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Order')
            ->setEntityLabelInPlural('Orders')
            ->setPageTitle('index', 'Orders Management')
            ->setSearchFields(['orderNumber', 'user.email', 'user.firstName', 'user.lastName', 'status'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(25);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->disable(Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->onlyOnIndex();
        yield TextField::new('orderNumber', 'Order Number')
            ->setLabel('Order #')
            ->setDisabled();
        yield AssociationField::new('user')
            ->setDisabled();
        yield ChoiceField::new('status')
            ->setChoices([
                'Pending' => OrderStatus::PENDING->value,
                'Paid' => OrderStatus::PAID->value,
                'Shipped' => OrderStatus::SHIPPED->value,
                'Delivered' => OrderStatus::DELIVERED->value,
                'Cancelled' => OrderStatus::CANCELLED->value,
            ])
            ->renderAsBadges([
                OrderStatus::PENDING->value => 'warning',
                OrderStatus::PAID->value => 'success',
                OrderStatus::SHIPPED->value => 'primary',
                OrderStatus::DELIVERED->value => 'success',
                OrderStatus::CANCELLED->value => 'danger',
            ]);
        yield MoneyField::new('totalAmount', 'Total Amount')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setDisabled();
        yield CollectionField::new('orderItems', 'Order Items')
            ->setEntryType(OrderItemType::class)
            ->onlyOnForms();
        yield AssociationField::new('shippingAddress', 'Shipping Address')
            ->onlyOnDetail();
        yield AssociationField::new('billingAddress', 'Billing Address')
            ->onlyOnDetail();
        yield DateTimeField::new('createdAt', 'Created At')
            ->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Updated At')
            ->onlyOnDetail();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('status')
            ->add('user')
            ->add('totalAmount')
            ->add('createdAt');
    }
}
