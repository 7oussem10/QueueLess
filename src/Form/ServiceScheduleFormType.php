<?php

namespace App\Form;

use App\Entity\ServiceSchedule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class ServiceScheduleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('openingTime', TimeType::class, [
                'label' => 'Heure d\'ouverture',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(message: 'L\'heure d\'ouverture est obligatoire.'),
                ],
            ])
            ->add('closingTime', TimeType::class, [
                'label' => 'Heure de fermeture',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(message: 'L\'heure de fermeture est obligatoire.'),
                ],
            ])
            ->add('slotDurationMinutes', IntegerType::class, [
                'label' => 'Durée d\'un créneau (minutes)',
                'attr' => ['class' => 'form-control', 'min' => 5, 'max' => 120],
                'constraints' => [
                    new NotBlank(message: 'La durée du créneau est obligatoire.'),
                    new Positive(message: 'La durée doit être supérieure à 0.'),
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Jour ouvré / actif pour les rendez-vous',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'Décochez si le service est fermé ce jour-là.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ServiceSchedule::class,
        ]);
    }
}
