<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Nom du produit',
                'attr' => ['placeholder' => 'Ex: Canapé d\'angle'],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer un nom de produit']),
                ],
            ])
            ->add('description', null, [
                'label' => 'Description',
                'attr' => ['placeholder' => 'Description détaillée du produit...']
            ])
            ->add('originalPrice', null, [
                'label' => 'Prix d\'origine (€) (Optionnel)',
                'attr' => ['placeholder' => '0.00'],
                'required' => false,
            ])
            ->add('price', null, [
                'label' => 'Prix (€)',
                'attr' => ['placeholder' => '0.00'],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer un prix']),
                ],
            ])
            ->add('stock', null, [
                'label' => 'Stock disponible',
                'attr' => ['placeholder' => '0']
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image du produit',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG, WEBP)',
                    ])
                ],
            ])
            ->add('category', \Symfony\Bridge\Doctrine\Form\Type\EntityType::class, [
                'class' => \App\Entity\Category::class,
                'choice_label' => 'name',
                'label' => 'Catégorie',
                'placeholder' => '-- Choisir une catégorie --',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
