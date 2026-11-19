<?php

namespace App\Form;

use App\Entity\Counter;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CounterFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du guichet',
                'attr' => ['placeholder' => 'Ex : Guichet 1', 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(message: 'Le nom du guichet est obligatoire.'),
                ],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code / Référence guichet',
                'attr' => ['placeholder' => 'Ex : G1', 'class' => 'form-control text-uppercase'],
                'constraints' => [
                    new NotBlank(message: 'Le code du guichet est obligatoire.'),
                ],
            ])
            ->add('service', EntityType::class, [
                'class' => Service::class,
                'choice_label' => fn(Service $s) => $s->getName() . ' [' . $s->getCode() . ']',
                'label' => 'Service assigné',
                'required' => false,
                'placeholder' => '-- Aucun service assigné --',
                'attr' => ['class' => 'form-select'],
                'help' => 'Associez ce guichet au service qu\'il traite en priorité.',
            ])
            ->add('currentAgent', EntityType::class, [
                'class' => User::class,
                'query_builder' => function (UserRepository $ur) {
                    return $ur->createQueryBuilder('u')
                        ->where('u.roles LIKE :role')
                        ->setParameter('role', '%"ROLE_AGENT"%')
                        ->andWhere('u.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('u.lastName', 'ASC');
                },
                'choice_label' => fn(User $u) => $u->getFullName() . ' (' . $u->getEmail() . ')',
                'label' => 'Agent affecté (optionnel)',
                'required' => false,
                'placeholder' => '-- Aucun agent affecté actuellement --',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Guichet opérationnel / actif',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'Décochez si le guichet est temporairement fermé.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Counter::class,
        ]);
    }
}
