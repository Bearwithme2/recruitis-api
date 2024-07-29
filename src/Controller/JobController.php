<?php
// src/Controller/JobController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\RecruitisApiClient;

class JobController extends AbstractController
{
    private RecruitisApiClient $apiClient;

    public function __construct(RecruitisApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    #[Route('/jobs/{page}', name: 'job_list', requirements: ['page' => '\d+'], defaults: ['page' => 1])]
    public function list(int $page): Response
    {
        try {
            $jobs = $this->apiClient->getJobListings($page);
        } catch (\Throwable $e) {
            return $this->render('error.html.twig', ['message' => $e->getMessage()]);
        }

        return $this->render('job/list.html.twig', [
            'jobs' => $jobs,
            'page' => $page,
        ]);
    }
}

