<?php

declare(strict_types=1);

namespace WPAgents\WordPress\Admin;

use InvalidArgumentException;
use WPAgents\Contracts\SkillRepository;
use WPAgents\Domain\Skill\SkillMarkdown;
use WPAgents\WordPress\Capabilities;
use WPAgents\WordPress\Urls;

/**
 * Brings in a skill written as a SKILL.md file: pasted, or uploaded.
 */
final class ImportSkillPage
{
    private const MAX_BYTES = 200000;

    public function __construct(private readonly SkillRepository $skills)
    {
    }

    public function register(): void
    {
        add_action('admin_post_wpa_import_skill', [$this, 'import']);
    }

    public function import(): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to do that.', 'wp-agents'), '', ['response' => 403]);
        }

        check_admin_referer('wpa_import_skill');

        $markdown = isset($_POST['skill_markdown']) ? (string) wp_unslash($_POST['skill_markdown']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed and stored as text, never output raw.

        $file = isset($_FILES['skill_file']) && is_array($_FILES['skill_file']) ? $_FILES['skill_file'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $tmp = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
        if ($tmp !== '' && is_uploaded_file($tmp) && (int) ($file['size'] ?? 0) <= self::MAX_BYTES) {
            $contents = file_get_contents($tmp); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading an uploaded temp file.
            $markdown = is_string($contents) ? $contents : $markdown;
        }

        if (strlen($markdown) > self::MAX_BYTES) {
            Flash::redirect(Urls::importSkill(), 'error', __('That file is too large for a skill.', 'wp-agents'));
        }

        try {
            $skill = SkillMarkdown::parse($markdown);
        } catch (InvalidArgumentException $e) {
            Flash::redirect(Urls::importSkill(), 'error', $e->getMessage());
        }

        $this->skills->save($skill);

        Flash::redirect(
            admin_url('edit.php?post_type=' . \WPAgents\Infrastructure\PostTypes::SKILL),
            'success',
            sprintf(
                /* translators: %s: skill name */
                __('Imported the skill “%s”.', 'wp-agents'),
                $skill->name
            )
        );
    }

    public function render(): void
    {
        if (! Capabilities::canManage()) {
            wp_die(esc_html__('You are not allowed to see this page.', 'wp-agents'), '', ['response' => 403]);
        }

        echo '<div class="wrap wpa-wrap"><h1>' . esc_html__('Import a skill', 'wp-agents') . '</h1>';
        Flash::render();
        echo '<p>' . esc_html__('A skill is a SKILL.md file: a short header with a name and description, then the instructions. Importing a skill with the same name updates it.', 'wp-agents') . '</p>';

        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('wpa_import_skill');
        echo '<input type="hidden" name="action" value="wpa_import_skill">';
        echo '<p><label for="wpa-skill-file"><strong>' . esc_html__('Upload a file', 'wp-agents') . '</strong></label><br><input type="file" id="wpa-skill-file" name="skill_file" accept=".md,.markdown,.txt,text/markdown,text/plain"></p>';
        echo '<p><label for="wpa-skill-text"><strong>' . esc_html__('Or paste it here', 'wp-agents') . '</strong></label></p>';
        echo '<p><textarea id="wpa-skill-text" name="skill_markdown" rows="16" class="large-text code" placeholder="---&#10;name: Release notes writer&#10;description: Turns a list of changes into friendly release notes.&#10;---&#10;&#10;Write the notes in plain language…"></textarea></p>';
        submit_button(__('Import skill', 'wp-agents'));
        echo '</form></div>';
    }
}
