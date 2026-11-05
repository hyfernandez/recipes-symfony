<?php

namespace App\Form;

use App\Entity\Recipe;
use App\Entity\Tag;
use App\Enum\Difficulty;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RecipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // empty_data: an empty field would submit null, but the entity setters
        // only accept non-null values. With empty_data the validator reports the
        // problem (NotBlank/Positive) instead of PHP throwing a TypeError.
        $builder
            ->add('title', TextType::class, ['empty_data' => ''])
            ->add('description', TextareaType::class, ['empty_data' => '', 'attr' => ['rows' => 6]])
            ->add('prepTimeMinutes', IntegerType::class, ['label' => 'Preparation time (minutes)', 'empty_data' => '0'])
            ->add('difficulty', EnumType::class, [
                'class' => Difficulty::class,
                'choice_label' => fn (Difficulty $d) => ucfirst($d->value),
                'placeholder' => false,
            ])
            // Not mapped: the controller hands the file to ImageUploader and stores only the filename.
            ->add('imageFile', FileType::class, [
                'label' => 'Photo (jpg, png or webp, max 2 MB)',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
            ])
            ->add('tags', EntityType::class, [
                'class' => Tag::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,   // checkboxes
                'required' => false,
                'by_reference' => false, // use Recipe::addTag()/removeTag()
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Recipe::class]);
    }
}
