<?php

namespace App\Form;

use App\Entity\Service;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class ServiceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du service',
                'attr' => ['placeholder' => 'Ex : Accueil & Renseignements', 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(message: 'Le nom du service est obligatoire.'),
                ],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code abrégé (pour les numéros de ticket)',
                'attr' => ['placeholder' => 'Ex : ACC', 'class' => 'form-control text-uppercase'],
                'help' => 'Code unique en majuscules (ex: ACC, DOC, FAC) servant de préfixe aux tickets.',
                'constraints' => [
                    new NotBlank(message: 'Le code est obligatoire.'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Décrivez les prestations couvertes par ce service...', 'class' => 'form-control'],
            ])
            ->add('estimatedDurationMinutes', IntegerType::class, [
                'label' => 'Durée moyenne estimée par passage (minutes)',
                'attr' => ['min' => 1, 'max' => 180, 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(message: 'La durée estimée est obligatoire.'),
                    new Positive(message: 'La durée doit être strictement positive.'),
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Service ouvert / actif',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'Si décoché, les clients ne pourront pas prendre de ticket ou réserver ce service.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
        ]);
    }
}
