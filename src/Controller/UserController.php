<?php

namespace App\Controller;

use App\Form\ChangePasswordFormType;
use App\Form\SearchSortieUserFormType;
use App\Form\UserEditFormType;
use App\Repository\SortieRepository;
use App\Repository\UserRepository;
use App\Services\SearchSortie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    /**
     * @Route("/user", name="app_user")
     */
    public function index(): Response
    {
        return $this->render('user/index.html.twig');
    }

    /**
     * @Route("/user/edit", name="app_user_edit")
     */
    public function edit(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->find($this->getUser());

        $userForm = $this->createForm(UserEditFormType::class, $user);
        try {
            $userForm = $userForm->handleRequest($request);
        }
        catch (InvalidArgumentException $e) {
            $this->addFlash('error', 'Champs invalides. Email et/ou Pseudo vide');
        }

        if ($userForm->isSubmitted() && $userForm->isValid()) {
            if ($userForm->get('image')->getData()) {
                if ($user->getUrlImage() && !preg_match('#'.'http'.'#', $user->getUrlImage())) {
                    unlink($this->getParameter('image_user_directory') . '/' . $user->getUrlImage());
                }

                $image = $userForm->get('image')->getData();
                $urlImage = md5(uniqid()) . '.' . $image->guessExtension();
                $image->move($this->getParameter('image_user_directory'), $urlImage);
                $user->setUrlImage($urlImage);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Le profil a été modifié');

            return $this->redirectToRoute('app_user');
        }

        return $this->render('user/edit.html.twig', [
            'userForm' => $userForm->createView(),
            'user' => $user,
        ]);
    }

    /**
     * @Route("/user/edit_password", name="app_user_edit_password")
     */
    public function editPassword(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->find($this->getUser());

        $changePasswordFormType = $this->createForm(ChangePasswordFormType::class, $user);
        $changePasswordFormType = $changePasswordFormType->handleRequest($request);

        if ($changePasswordFormType->isSubmitted() && $changePasswordFormType->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Le mot de passe a été modifié');

            return $this->redirectToRoute('app_user');
        }

        return $this->render('user/edit_password.html.twig', [
            'changePasswordFormType' => $changePasswordFormType->createView(),
            'user' => $user,
        ]);
    }

    /**
     * @Route("/user/sortie", name="app_user_sortie")
     */
    public function sortie(Request $request, SortieRepository $sortieRepository, UserRepository $userRepository): Response
    {
        $searchSortie = new SearchSortie();
        $user = $userRepository->find($this->getUser());
        $searchSortie->page = $request->get('page', 1);
        if ($request->get('checkbox') === 'organise') {
            $searchSortie->organisateur = $user;
        }
        elseif ($request->get('checkbox') === 'participe') {
            $searchSortie->participant = $user;
        }
        else {
            $searchSortie->both = $user;
        }

        $searchSortieUserFormType = $this->createForm(SearchSortieUserFormType::class, $searchSortie);
        $searchSortieUserFormType->handleRequest($request);

        $tableauSorties = $sortieRepository->findSearchSortiePaginate($searchSortie, 12);
        $nbreResultats = $sortieRepository->countResultSearchSortie($searchSortie);

        return $this->render('user/sortie.html.twig', [
            'searchSortieUserFormType' => $searchSortieUserFormType->createView(),
            'tableauSorties' => $tableauSorties,
            'nbreResultats' => $nbreResultats,
        ]);
    }
}
