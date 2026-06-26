<?php
declare(strict_types=1);

class CVAnalyzer
{
    private OpenAIService $ai;

    public function __construct(int $tenantId)
    {
        $this->ai = new OpenAIService($tenantId);
    }

    /**
     * Analyze a CV against a job posting and persist results.
     *
     * @return array  Analysis data with keys: match_score, skills_extracted, companies,
     *                years_exp, education, strengths, weaknesses, notes.
     */
    public function analyze(
        int    $applicationId,
        int    $documentId,
        string $cvText,
        string $jobTitle,
        string $seniority,
        string $jobDescription,
        string $requirements
    ): array {
        $default = [
            'match_score'       => 0,
            'skills_extracted'  => [],
            'companies'         => [],
            'years_exp'         => 0,
            'education'         => null,
            'strengths'         => [],
            'weaknesses'        => [],
            'notes'             => null,
        ];

        try {
            $db = Database::getInstance();
            $now = date('Y-m-d H:i:s');

            // Load prompt template (system + user) if available
            $template = $db->fetch(
                "SELECT system_prompt, user_prompt_template, max_tokens, temperature
                 FROM ai_prompt_templates
                 WHERE slug = 'cv_analysis' AND (tenant_id IS NULL OR tenant_id = ?)
                 ORDER BY tenant_id DESC LIMIT 1",
                [$db->getTenantId() ?? 0]
            );

            if ($template && !empty($template['user_prompt_template'])) {
                $userPrompt = str_replace(
                    ['{job_title}', '{seniority}', '{job_description}', '{requirements}', '{cv_text}'],
                    [$jobTitle, $seniority, $jobDescription, $requirements, $cvText],
                    $template['user_prompt_template']
                );
                $systemPrompt = $template['system_prompt'] ?? null;
                $maxTokens    = (int)($template['max_tokens'] ?? 1500);
                $temperature  = (float)($template['temperature'] ?? 0.3);
            } else {
                $systemPrompt = 'You are an expert recruiter and CV analyst. Respond only with valid JSON.';
                $userPrompt   = "Analyze this CV for the role of {$jobTitle} ({$seniority}).\n\n"
                    . "Job Description:\n{$jobDescription}\n\n"
                    . "Requirements:\n{$requirements}\n\n"
                    . "CV:\n{$cvText}\n\n"
                    . "Return JSON: {\"match_score\": 0-100, \"skills_extracted\": [], \"companies\": [], "
                    . "\"years_experience\": 0, \"education_level\": \"\", \"strengths\": [], \"weaknesses\": [], \"notes\": \"\"}";
                $maxTokens   = 1500;
                $temperature = 0.3;
            }

            $messages = [];
            if ($systemPrompt) {
                $messages[] = ['role' => 'system', 'content' => $systemPrompt];
            }
            $messages[] = ['role' => 'user', 'content' => $userPrompt];

            $response = $this->ai->chat($messages, [
                'max_tokens'      => $maxTokens,
                'temperature'     => $temperature,
                'response_format' => ['type' => 'json_object'],
                'feature'         => 'cv_analysis',
            ]);

            if (!$response) {
                return $default;
            }

            $data = $this->ai->getJSON($response);
            if (!$data) {
                return $default;
            }

            $result = [
                'match_score'      => min(100, max(0, (float)($data['match_score'] ?? 0))),
                'skills_extracted' => $data['skills_extracted'] ?? [],
                'companies'        => $data['companies'] ?? [],
                'years_exp'        => (float)($data['years_experience'] ?? 0),
                'education'        => $data['education_level'] ?? null,
                'strengths'        => $data['strengths'] ?? [],
                'weaknesses'       => $data['weaknesses'] ?? [],
                'notes'            => $data['notes'] ?? null,
            ];

            $tokensUsed = $response['usage']['total_tokens'] ?? null;
            $rawJson    = json_encode($data);

            // Upsert into ai_cv_analyses
            $existing = $db->fetch(
                "SELECT id FROM ai_cv_analyses WHERE application_id = ? LIMIT 1",
                [$applicationId]
            );

            $record = [
                'application_id'      => $applicationId,
                'document_id'         => $documentId,
                'match_score'         => $result['match_score'],
                'skills_extracted'    => json_encode($result['skills_extracted']),
                'companies_extracted' => json_encode($result['companies']),
                'years_experience'    => $result['years_exp'],
                'education_level'     => $result['education'],
                'strengths'           => json_encode($result['strengths']),
                'weaknesses'          => json_encode($result['weaknesses']),
                'notes'               => $result['notes'],
                'raw_response'        => $rawJson,
                'tokens_used'         => $tokensUsed,
                'analyzed_at'         => $now,
                'updated_at'          => $now,
            ];

            if ($existing) {
                unset($record['application_id']);
                $db->update('ai_cv_analyses', $record, ['id' => (int)$existing['id']]);
            } else {
                $record['created_at'] = $now;
                $db->insert('ai_cv_analyses', $record);
            }

            return $result;
        } catch (\Throwable $e) {
            error_log("CVAnalyzer::analyze failed for application {$applicationId}: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Extract text from a PDF file using pdftotext, falling back to basic binary read.
     */
    public function extractTextFromPDF(string $filePath): string
    {
        if (!file_exists($filePath)) {
            return '';
        }

        // Try pdftotext if available
        $pdftotext = trim((string)shell_exec('which pdftotext 2>/dev/null'));
        if ($pdftotext) {
            $escaped = escapeshellarg($filePath);
            $text    = shell_exec("{$pdftotext} {$escaped} - 2>/dev/null");
            if ($text !== null && trim($text) !== '') {
                return trim($text);
            }
        }

        // Basic fallback: read raw bytes and extract printable ASCII
        $raw = @file_get_contents($filePath);
        if ($raw === false) {
            return '';
        }
        // Strip binary noise; keep printable chars and whitespace
        $text = preg_replace('/[^\x20-\x7E\x09\x0A\x0D]/u', ' ', $raw) ?? '';
        $text = preg_replace('/\s{3,}/', "\n", $text) ?? '';

        return trim($text);
    }

    /**
     * Extract text from a .doc or .docx file.
     * For .docx extracts word/document.xml content; for .doc reads printable chars.
     */
    public function extractTextFromDoc(string $filePath): string
    {
        if (!file_exists($filePath)) {
            return '';
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'docx') {
            // .docx is a ZIP archive; extract word/document.xml
            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($filePath) === true) {
                    $xml = $zip->getFromName('word/document.xml');
                    $zip->close();
                    if ($xml !== false) {
                        // Strip XML tags, decode entities
                        $text = strip_tags(str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml));
                        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
                        return trim(preg_replace('/\s{3,}/', "\n", $text) ?? $text);
                    }
                }
            }
        }

        // Fallback for .doc or failed .docx: read printable chars from binary
        $raw = @file_get_contents($filePath);
        if ($raw === false) {
            return '';
        }
        $text = preg_replace('/[^\x20-\x7E\x09\x0A\x0D]/u', ' ', $raw) ?? '';
        $text = preg_replace('/\s{3,}/', "\n", $text) ?? '';

        return trim($text);
    }
}
