<?php

namespace App\Controller;

use App\Constant\Route;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\AppAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class RegistrationController extends AbstractController
{
    #[RouteAttribute('/register', name: Route::REGISTER->value)]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher, 
        EntityManagerInterface $entityManager, 
        UserAuthenticatorInterface $userAuthenticator, 
        AppAuthenticator $authenticator
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute(Route::HOME->value);
        }

        // Logique d'enregistrement de l'utilisateur
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Encoder le mot de passe
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $user->setRoles(['ROLE_USER']);
            $user->setCreatedAt(new \DateTimeImmutable());

            // Enregistrer l'utilisateur en base de données
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Votre compte a été créé avec succès.');

            // Rediriger ou connecter automatiquement l'utilisateur
            return $userAuthenticator->authenticateUser(
                $user,
                $authenticator,
                $request
            );
        }
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /*
    #[Route('/verify-email', name: '_verify_email')]
    public function verifyEmail(): Response
    {
        // Logique de vérification de l'email
    }
    */
}