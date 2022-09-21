<?php

declare(strict_types=1);

namespace App\Form\Generator;

use App\Form\Generator\AbstractGeneratorType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;

final class GlobalOptionsType extends AbstractGeneratorType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('appleM1Chip', CheckboxType::class, [
                'label'    => 'Are you using M1 chip (Mac)',
                'required' => false,
            ])
            ->add('basePort', IntegerType::class, [
                'label'       => 'Base port',
                'attr'        => ['placeholder' => 'For nginx, Mailhog control panel...'],
                'data'        => 8081,
                'constraints' => [
                    new NotBlank(),
                    new Type(type: 'integer'),
                    new Range(min: 1025, max: 65535),
                ],
            ])
            ->add('projectName', TextType::class, [
                'label' => 'Enter project name',
                'attr'  => ['placeholder' => 'One Ring'],
                'data'  => 'SSmysite',
            ])
            ->add('appPath', HiddenType::class, [
                'data' => '.',
            ])
            ->add('dockerWorkingDir', HiddenType::class, [
                'data' => '/var/www/html',
            ]);
    }
}
