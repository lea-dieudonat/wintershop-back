<?php
namespace App\Form;

use App\Entity\OrderItem;
use App\Entity\Product;
use Dom\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class OrderItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', Entity::class, [
                'class' => Product::class,
                'choice_label' => 'name',
                'disabled' => true,
            ])
            ->add('quantity', IntegerType::class, [
                'attr' => ['min' => 1],
                'disabled' => true,
            ])
            ->add('unitPrice', NumberType::class, [
                'scale' => 2,
                'disabled' => true,
                'label' => 'Unit Price (€)',
            ])
            ->add('totalPrice', NumberType::class, [
                'scale' => 2,
                'disabled' => true,
                'label' => 'Total Price (€)',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OrderItem::class,
        ]);
    }
}