<?php

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory as ComponentsFactory;
use Illuminate\Filesystem\Filesystem;
use Lvntr\StarterKit\Console\Commands\Concerns\MirrorsAiSkills;
use Lvntr\StarterKit\Console\Commands\InstallCommand;
use Lvntr\StarterKit\StarterKitServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class AiSkillMirrorHarness extends Command
{
    use MirrorsAiSkills;

    protected $signature = 'test:ai-skill-mirror';

    private Filesystem $files;

    private BufferedOutput $buffer;

    public function __construct()
    {
        parent::__construct();

        $this->files = new Filesystem;
        $this->buffer = new BufferedOutput;

        $input = new ArrayInput([], $this->getDefinition());
        $style = new OutputStyle($input, $this->buffer);

        $this->setInput($input);
        $this->setOutput($style);
        $this->components = new ComponentsFactory($style);
    }

    public function mirror(bool $dryRun = false, bool $skipped = false): int
    {
        return $this->mirrorAiSkills($dryRun, $skipped);
    }

    /**
     * @param  array<string, string>  $hashes
     */
    public function registrySkipsMirror(array $hashes): bool
    {
        return $this->aiSkillsWereSkipped($hashes);
    }

    public function outputText(): string
    {
        return $this->buffer->fetch();
    }
}

function aiSkillMirrorFilesystem(): Filesystem
{
    return new Filesystem;
}

function seedPublishedAiSkills(): void
{
    aiSkillMirrorFilesystem()->copyDirectory(
        StarterKitServiceProvider::stubsPath('.claude/skills'),
        base_path('.claude/skills'),
    );
}

/**
 * @return list<string>
 */
function aiSkillMirrorRelativeFiles(string $path): array
{
    $files = aiSkillMirrorFilesystem()->allFiles($path, true);
    $relativePaths = array_map(
        fn ($file): string => str_replace('\\', '/', $file->getRelativePathname()),
        $files,
    );

    sort($relativePaths);

    return $relativePaths;
}

function firstKitAiSkillName(): string
{
    $directories = aiSkillMirrorFilesystem()->directories(
        StarterKitServiceProvider::stubsPath('.claude/skills'),
    );

    return basename($directories[0]);
}

function cleanupAiSkillMirrorFixtures(): void
{
    $files = aiSkillMirrorFilesystem();

    foreach (['.claude/skills', '.codex/skills'] as $path) {
        $target = base_path($path);

        if ($files->isDirectory($target)) {
            $files->deleteDirectory($target);
        }
    }
}

beforeEach(function (): void {
    cleanupAiSkillMirrorFixtures();
});

afterEach(function (): void {
    cleanupAiSkillMirrorFixtures();
});

it('publishes Codex skills with the same relative files and contents as Claude skills', function (): void {
    seedPublishedAiSkills();

    $harness = new AiSkillMirrorHarness;
    $count = $harness->mirror();

    $claudeRoot = base_path('.claude/skills');
    $codexRoot = base_path('.codex/skills');
    $relativeFiles = aiSkillMirrorRelativeFiles($claudeRoot);

    expect(aiSkillMirrorRelativeFiles($codexRoot))->toBe($relativeFiles)
        ->and($count)->toBe(count($relativeFiles))
        ->and($harness->outputText())->toContain(
            "AI skills mirrored to .codex/skills ({$count} files)",
        );

    foreach ($relativeFiles as $relativeFile) {
        expect(aiSkillMirrorFilesystem()->get($codexRoot.'/'.$relativeFile))
            ->toBe(aiSkillMirrorFilesystem()->get($claudeRoot.'/'.$relativeFile));
    }
});

it('marks Claude skills as skip paths for --without-ai-skill installs', function (): void {
    $command = new InstallCommand;
    $command->setInput(new ArrayInput(
        ['--without-ai-skill' => true],
        $command->getDefinition(),
    ));

    $skipPaths = new ReflectionMethod($command, 'getSkipPaths');

    expect($skipPaths->invoke($command))->toContain('.claude/skills/');
});

it('does not touch Codex skills when the mirror is skipped', function (): void {
    seedPublishedAiSkills();

    $preExisting = base_path('.codex/skills/'.firstKitAiSkillName().'/SKILL.md');
    aiSkillMirrorFilesystem()->ensureDirectoryExists(dirname($preExisting));
    aiSkillMirrorFilesystem()->put($preExisting, "pre-existing\n");

    $harness = new AiSkillMirrorHarness;
    $count = $harness->mirror(skipped: true);

    expect($count)->toBe(0)
        ->and(aiSkillMirrorFilesystem()->get($preExisting))->toBe("pre-existing\n");
});

it('leaves an existing Codex skill dir untouched when its Claude source is absent', function (): void {
    seedPublishedAiSkills();

    $skillName = firstKitAiSkillName();
    aiSkillMirrorFilesystem()->deleteDirectory(base_path('.claude/skills/'.$skillName));

    $preserved = base_path(".codex/skills/{$skillName}/SKILL.md");
    aiSkillMirrorFilesystem()->ensureDirectoryExists(dirname($preserved));
    aiSkillMirrorFilesystem()->put($preserved, "authored directly\n");

    (new AiSkillMirrorHarness)->mirror();

    expect(aiSkillMirrorFilesystem()->get($preserved))->toBe("authored directly\n");
});

it('stays silent when there is nothing to mirror', function (): void {
    aiSkillMirrorFilesystem()->ensureDirectoryExists(base_path('.claude/skills/unrelated'));

    $harness = new AiSkillMirrorHarness;

    expect($harness->mirror())->toBe(0)
        ->and($harness->outputText())->not->toContain('mirrored');
});

it('re-syncs Codex skills from consumer-customized Claude content', function (): void {
    seedPublishedAiSkills();

    $harness = new AiSkillMirrorHarness;
    $harness->mirror();

    $skillName = firstKitAiSkillName();
    $sourceFile = aiSkillMirrorRelativeFiles(base_path('.claude/skills/'.$skillName))[0];
    $customized = "consumer customization\n";

    aiSkillMirrorFilesystem()->put(
        base_path(".claude/skills/{$skillName}/{$sourceFile}"),
        $customized,
    );

    $harness->mirror();

    expect(aiSkillMirrorFilesystem()->get(
        base_path(".codex/skills/{$skillName}/{$sourceFile}"),
    ))->toBe($customized);
});

it('leaves foreign Codex skill directories untouched', function (): void {
    seedPublishedAiSkills();

    $foreignFile = base_path('.codex/skills/my-own-skill/SKILL.md');
    aiSkillMirrorFilesystem()->ensureDirectoryExists(dirname($foreignFile));
    aiSkillMirrorFilesystem()->put($foreignFile, "consumer-owned\n");

    (new AiSkillMirrorHarness)->mirror();

    expect(aiSkillMirrorFilesystem()->get($foreignFile))->toBe("consumer-owned\n");
});

it('does not mirror when the registry marks Claude skills as skipped', function (): void {
    seedPublishedAiSkills();

    $harness = new AiSkillMirrorHarness;
    $skipped = $harness->registrySkipsMirror([
        '.claude/skills/'.firstKitAiSkillName().'/SKILL.md' => '__skipped__',
    ]);

    $harness->mirror(skipped: $skipped);

    expect(base_path('.codex/skills'))->not->toBeDirectory();
});

it('reports a dry-run without changing Codex skills', function (): void {
    seedPublishedAiSkills();

    $skillName = firstKitAiSkillName();
    $staleFile = base_path(".codex/skills/{$skillName}/stale.txt");
    aiSkillMirrorFilesystem()->ensureDirectoryExists(dirname($staleFile));
    aiSkillMirrorFilesystem()->put($staleFile, "stale\n");

    $harness = new AiSkillMirrorHarness;
    $count = $harness->mirror(dryRun: true);

    expect(aiSkillMirrorFilesystem()->get($staleFile))->toBe("stale\n")
        ->and($harness->outputText())->toContain(
            "AI skills would be mirrored to .codex/skills ({$count} files)",
        );
});
