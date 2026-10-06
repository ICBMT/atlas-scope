<?php

declare(strict_types=1);

namespace Atlas\Scope\Services\Scan;

use Atlas\Scope\Enums\Language;
use Atlas\Scope\Enums\ScanStage as StageEnum;
use Atlas\Scope\Services\Scan\Languages\Cxx\DeclarationParser;
use Atlas\Scope\Services\Scan\Languages\Cxx\Stages\BuildStage;
use Atlas\Scope\Services\Scan\Languages\Cxx\Stages\CallStage;
use Atlas\Scope\Services\Scan\Languages\Cxx\Stages\DeclarationStage;
use Atlas\Scope\Services\Scan\Languages\Cxx\Stages\FileIndexStage as CxxFileIndexStage;
use Atlas\Scope\Services\Scan\Languages\Cxx\Stages\ReferenceStage;
use Atlas\Scope\Services\Scan\Languages\Cxx\SymbolClassifier;
use Atlas\Scope\Services\Scan\Languages\LanguageProfile;
use Atlas\Scope\Services\Scan\Languages\Python\PythonIndex;
use Atlas\Scope\Services\Scan\Languages\Python\PythonParser;
use Atlas\Scope\Services\Scan\Languages\Python\PythonSymbolClassifier;
use Atlas\Scope\Services\Scan\Languages\Python\Stages\BuildStage as PythonBuildStage;
use Atlas\Scope\Services\Scan\Languages\Python\Stages\CallStage as PythonCallStage;
use Atlas\Scope\Services\Scan\Languages\Python\Stages\DeclarationStage as PythonDeclarationStage;
use Atlas\Scope\Services\Scan\Languages\Python\Stages\ModelStage as PythonModelStage;
use Atlas\Scope\Services\Scan\Languages\Python\Stages\ReferenceStage as PythonReferenceStage;
use Atlas\Scope\Services\Scan\Languages\Python\Stages\RouteStage as PythonRouteStage;
use Atlas\Scope\Services\Scan\Languages\Python\Stages\ViewStage as PythonViewStage;
use Atlas\Scope\Services\Scan\Languages\PythonProfile;
use Atlas\Scope\Services\Scan\Languages\ProfileRegistry;
use Atlas\Scope\Services\Scan\Parsers\BladeParser;
use Atlas\Scope\Services\Scan\Parsers\MigrationParser;
use Atlas\Scope\Services\Scan\Parsers\PhpFileAnalyzer;
use Atlas\Scope\Services\Scan\Parsers\RouteParser;
use Atlas\Scope\Services\Scan\Stages\ClassParseStage;
use Atlas\Scope\Services\Scan\Stages\ExtractStage;
use Atlas\Scope\Services\Scan\Stages\FileIndexStage;
use Atlas\Scope\Services\Scan\Stages\InsightsStage;
use Atlas\Scope\Services\Scan\Stages\LayoutStage;
use Atlas\Scope\Services\Scan\Stages\LinkStage;
use Atlas\Scope\Services\Scan\Stages\ManifestStage;
use Atlas\Scope\Services\Scan\Stages\ModelStage;
use Atlas\Scope\Services\Scan\Stages\RouteStage;
use Atlas\Scope\Services\Scan\Stages\SchemaStage;
use Atlas\Scope\Services\Scan\Stages\ViewStage;

/**
 * Assembles the right pipeline for a language.
 *
 * The stage list lives here, in one place, so it is impossible to get the order
 * wrong and easy to see what each language actually runs. The Laravel plan is
 * untouched by the C-family work: `Language::Php` builds exactly the stages it
 * always has.
 */
class ScanPipelineFactory
{
    /** @var array<string, ScanPipeline> */
    private array $pipelines = [];

    public function __construct(private readonly ProfileRegistry $registry) {}

    public function for(Language $language): ScanPipeline
    {
        return $this->pipelines[$language->value] ??= new ScanPipeline($this->stages($language));
    }

    /** @return array<int, Stage> */
    public function stages(Language $language): array
    {
        $profile = $this->registry->for($language);

        $stages = [new ExtractStage];

        foreach ($profile->plan() as $stage) {
            $stages[] = $this->stage($stage, $profile);
        }

        return $stages;
    }

    /** The stage list as enum values — handy for diagnostics and tests. */
    public function planNames(Language $language): array
    {
        return array_values(array_filter(array_map(
            fn (Stage $stage) => $stage->name()->value,
            $this->stages($language),
        )));
    }

    private function stage(StageEnum $stage, LanguageProfile $profile): Stage
    {
        $isPhp = $profile->language() === Language::Php;
        $isPython = $profile->language() === Language::Python;

        return match ($stage) {
            // ---- Laravel ----------------------------------------------------
            StageEnum::Files => $isPhp
                ? new FileIndexStage(new ClassClassifier)
                : new CxxFileIndexStage($profile),
            StageEnum::Manifest => new ManifestStage,
            StageEnum::Classes => new ClassParseStage(new PhpFileAnalyzer, new ClassClassifier),
            StageEnum::Routes => $isPython
                ? new PythonRouteStage(new PythonIndex(new PythonParser))
                : new RouteStage(new RouteParser),
            StageEnum::Models => $isPython
                ? new PythonModelStage(new PythonIndex(new PythonParser))
                : new ModelStage,
            StageEnum::Schema => new SchemaStage(new MigrationParser),
            StageEnum::Views => $isPython
                ? new PythonViewStage(new PythonIndex(new PythonParser))
                : new ViewStage(new BladeParser),
            StageEnum::Links => new LinkStage,

            // ---- Python -----------------------------------------------------
            StageEnum::Build => $isPython ? new PythonBuildStage(new PythonIndex(new PythonParser)) : new BuildStage,
            StageEnum::Declarations => $isPython
                ? new PythonDeclarationStage(new PythonIndex(new PythonParser), new PythonSymbolClassifier)
                : new DeclarationStage($profile, new DeclarationParser, new SymbolClassifier),
            StageEnum::References => $isPython
                ? new PythonReferenceStage(new PythonIndex(new PythonParser))
                : new ReferenceStage($profile),
            StageEnum::Calls => $isPython ? new PythonCallStage : new CallStage($profile),

            // ---- Shared -----------------------------------------------------
            StageEnum::Insights => new InsightsStage,
            StageEnum::Layout => new LayoutStage,
            default => throw new \RuntimeException('No stage implementation for '.$stage->value),
        };
    }
}
