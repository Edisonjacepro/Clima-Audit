<?php

namespace App\Form;

use App\Entity\Audit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class AuditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('address', TextType::class, [
                'label' => 'Adresse du site',
                'constraints' => [
                    new NotBlank(['message' => 'Merci de saisir une adresse complete.']),
                ],
            ])
            ->add('inputActivityType', ChoiceType::class, [
                'label' => 'Type d\'activite',
                'choices' => [
                    'Tertiaire' => 'tertiaire',
                    'Industrie' => 'industrie',
                    'Agricole' => 'agri',
                    'Collectivite' => 'collectivite',
                    'Autre' => 'autre',
                ],
                'placeholder' => 'Selectionner',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Merci de selectionner le type d\'activite.']),
                ],
            ])
            ->add('inputBuildingType', ChoiceType::class, [
                'label' => 'Type de batiment',
                'choices' => [
                    'Bureau' => 'bureau',
                    'Entrepot' => 'entrepot',
                    'ERP' => 'erp',
                    'Logement' => 'logement',
                    'Autre' => 'autre',
                ],
                'placeholder' => 'Selectionner',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Merci de selectionner le type de batiment.']),
                ],
            ])
            ->add('inputHasBasement', CheckboxType::class, [
                'label' => 'Presence d\'un sous-sol',
                'required' => false,
            ])
            ->add('inputCriticality', ChoiceType::class, [
                'label' => 'Criticite',
                'choices' => [
                    'Faible' => 'low',
                    'Moyenne' => 'medium',
                    'Elevee' => 'high',
                ],
                'placeholder' => 'Selectionner',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Merci de selectionner la criticite.']),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Lancer l\'audit',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Audit::class,
        ]);
    }
}
