<?php
namespace App\Modules\Jobs;

/**
 * Job business logic. Encodes/decodes JSON columns, applies defaults and
 * orchestrates AI-assisted job creation.
 */
class JobService
{
    private JobRepository $repository;

    public function __construct(?JobRepository $repository = null)
    {
        $this->repository = $repository ?? new JobRepository();
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function getJobs(int $tenantId, array $filters = []): array
    {
        return $this->repository->findAll($tenantId, $filters);
    }

    public function getJob(int $id, int $tenantId): ?array
    {
        return $this->repository->findById($id, $tenantId);
    }

    /**
     * Create a job. Sets created_by, defaults status to draft and JSON-encodes
     * ai_criteria / question_bank when supplied as arrays.
     *
     * @param array<string,mixed> $data
     */
    public function createJob(array $data, int $userId): int
    {
        $row = $this->mapWritableColumns($data);
        $row['created_by'] = $userId;
        $row['status'] = $this->normalizeStatus($data['status'] ?? 'draft');
        $row['job_type'] = $this->normalizeJobType($data['job_type'] ?? 'full-time');

        if (array_key_exists('ai_criteria', $data)) {
            $row['ai_criteria'] = $this->encodeJson($data['ai_criteria']);
        }
        if (array_key_exists('question_bank', $data)) {
            $row['question_bank'] = $this->encodeJson($data['question_bank']);
        }

        return $this->repository->create($row);
    }

    /**
     * Update a job. Only provided, writable columns are touched.
     *
     * @param array<string,mixed> $data
     */
    public function updateJob(int $id, array $data): int
    {
        $row = $this->mapWritableColumns($data);

        if (array_key_exists('status', $data)) {
            $row['status'] = $this->normalizeStatus($data['status']);
        }
        if (array_key_exists('job_type', $data)) {
            $row['job_type'] = $this->normalizeJobType($data['job_type']);
        }
        if (array_key_exists('ai_criteria', $data)) {
            $row['ai_criteria'] = $this->encodeJson($data['ai_criteria']);
        }
        if (array_key_exists('question_bank', $data)) {
            $row['question_bank'] = $this->encodeJson($data['question_bank']);
        }

        return $this->repository->update($id, $row);
    }

    public function deleteJob(int $id): int
    {
        return $this->repository->delete($id);
    }

    /**
     * Publish a job.
     */
    public function publishJob(int $id): int
    {
        return $this->repository->updateStatus($id, 'published');
    }

    /**
     * Build a structured job draft from a natural-language prompt. Delegates to
     * App\Modules\AI\JobBuilder when available; otherwise returns a sensible
     * structured fallback so the feature degrades gracefully.
     *
     * @return array<string,mixed>
     */
    public function buildJobWithAI(string $prompt, int $tenantId): array
    {
        $builderClass = 'App\\Modules\\AI\\JobBuilder';
        if (class_exists($builderClass) && method_exists($builderClass, 'buildFromPrompt')) {
            try {
                $builder = new $builderClass();
                $result = $builder->buildFromPrompt($prompt, $tenantId);
                if (is_array($result) && !empty($result)) {
                    return $result;
                }
            } catch (\Throwable $e) {
                logger('JobBuilder failed, using fallback: ' . $e->getMessage(), 'warning');
            }
        }

        return $this->fallbackJobStructure($prompt);
    }

    public function getRepository(): JobRepository
    {
        return $this->repository;
    }

    /**
     * Deterministic, dependency-free job scaffold derived from the prompt.
     *
     * @return array<string,mixed>
     */
    private function fallbackJobStructure(string $prompt): array
    {
        $prompt = trim($prompt);
        $title = $this->guessTitle($prompt);

        return [
            'title'       => $title,
            'description' => $prompt !== ''
                ? $prompt
                : 'We are seeking a motivated professional to join our team.',
            'requirements' => "- Relevant professional experience\n"
                . "- Strong communication and collaboration skills\n"
                . "- Ability to work independently and as part of a team\n"
                . "- Problem-solving mindset and attention to detail",
            'department' => '',
            'location'   => 'Remote',
            'job_type'   => 'full-time',
            'salary_currency' => 'USD',
            'ai_criteria' => [
                ['criterion_name' => 'Technical Skills', 'weight' => 30, 'description' => 'Depth of role-specific expertise.'],
                ['criterion_name' => 'Experience', 'weight' => 25, 'description' => 'Years and relevance of prior work.'],
                ['criterion_name' => 'Communication', 'weight' => 20, 'description' => 'Clarity and effectiveness of communication.'],
                ['criterion_name' => 'Culture Fit', 'weight' => 15, 'description' => 'Alignment with team values.'],
                ['criterion_name' => 'Problem Solving', 'weight' => 10, 'description' => 'Analytical and creative thinking.'],
            ],
            'question_bank' => [
                'Tell us about your most relevant experience for this role.',
                'Describe a challenging problem you solved recently.',
                'How do you prioritize competing tasks under a deadline?',
                'What interests you about this position?',
                'Where do you see yourself growing in this role?',
            ],
            'generated_by' => 'fallback',
        ];
    }

    private function guessTitle(string $prompt): string
    {
        if ($prompt === '') {
            return 'New Position';
        }
        // Use the first line / sentence as a title candidate, capped in length.
        $firstLine = preg_split('/[\r\n.]/', $prompt)[0] ?? $prompt;
        $firstLine = trim($firstLine);
        if ($firstLine === '') {
            return 'New Position';
        }
        return mb_substr($firstLine, 0, 120);
    }

    /**
     * Whitelist the scalar columns a caller may write directly.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function mapWritableColumns(array $data): array
    {
        $columns = [
            'title', 'description', 'requirements', 'department', 'location',
            'salary_min', 'salary_max', 'salary_currency', 'avatar_id',
        ];
        $row = [];
        foreach ($columns as $col) {
            if (array_key_exists($col, $data)) {
                $row[$col] = $data[$col] === '' ? null : $data[$col];
            }
        }
        return $row;
    }

    private function normalizeStatus($status): string
    {
        $allowed = ['draft', 'published', 'closed', 'archived'];
        $status = is_string($status) ? strtolower($status) : 'draft';
        return in_array($status, $allowed, true) ? $status : 'draft';
    }

    private function normalizeJobType($type): string
    {
        $allowed = ['full-time', 'part-time', 'contract', 'remote', 'internship'];
        $type = is_string($type) ? strtolower($type) : 'full-time';
        return in_array($type, $allowed, true) ? $type : 'full-time';
    }

    private function encodeJson($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            // Already-encoded JSON passes through; otherwise wrap as a scalar.
            json_decode($value);
            return json_last_error() === JSON_ERROR_NONE ? $value : json_encode($value);
        }
        return json_encode($value);
    }
}
