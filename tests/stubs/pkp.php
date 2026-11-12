<?php

/**
 * Test stand-ins only; these are not the real PKP implementation.
 */

namespace PKP\plugins {
    abstract class Plugin
    {
        public $pluginPath;

        public $pluginCategory;

        public function register($category, $path, $mainContextId = null)
        {
            $this->pluginCategory = $category;
            $this->pluginPath = $path;

            return true;
        }

        public function getCategory()
        {
            return $this->pluginCategory;
        }

        public function getPluginPath()
        {
            return $this->pluginPath;
        }

        abstract public function getName();

        abstract public function getDisplayName();

        abstract public function getDescription();
    }

    abstract class LazyLoadPlugin extends Plugin
    {
        public function getName()
        {
            return strtolower((new \ReflectionClass($this))->getShortName());
        }
    }

    abstract class GenericPlugin extends LazyLoadPlugin
    {
        public function getEnabled(): bool
        {
            return true;
        }
    }

    class Hook
    {
        /**
         * @var array<string, list<callable>>
         */
        public static array $hooks = [];

        public static function add(string $hookName, callable $callback, int $hookSequence = 256): void
        {
            self::$hooks[$hookName][] = $callback;
        }

        /**
         * @return list<callable>
         */
        public static function callbacksFor(string $hookName): array
        {
            return self::$hooks[$hookName] ?? [];
        }
    }
}

namespace PKP\core {
    class DataObject
    {
        /** @var array<string, mixed> */
        private array $data = [];

        public function getData($key, $locale = null): mixed
        {
            if (!is_string($key)) {
                return null;
            }

            $value = $this->data[$key] ?? null;

            if ($locale !== null && is_array($value) && is_string($locale)) {
                return $value[$locale] ?? null;
            }

            return $value;
        }

        public function setData($key, $value, $locale = null): void
        {
            if (!is_string($key)) {
                return;
            }

            if ($locale !== null && is_string($locale)) {
                if (!isset($this->data[$key]) || !is_array($this->data[$key])) {
                    $this->data[$key] = [];
                }

                $this->data[$key][$locale] = $value;

                return;
            }

            $this->data[$key] = $value;
        }

        public function getId(): ?int
        {
            $id = $this->getData('id');

            return is_int($id) ? $id : null;
        }
    }
}

namespace APP\submission {
    class Submission extends \PKP\core\DataObject
    {
    }

    class Repository
    {
        public function get(int $submissionId): ?Submission
        {
            return null;
        }
    }
}

namespace APP\publication {
    class Publication extends \PKP\core\DataObject
    {
    }

    class Repository
    {
        public function get(int $publicationId): ?Publication
        {
            return null;
        }
    }
}

namespace PKP\context {
    class Context extends \PKP\core\DataObject
    {
    }

    class ContextDAO
    {
        public function getById(int $contextId): ?Context
        {
            return null;
        }
    }
}

namespace PKP\author {
    class Author extends \PKP\core\DataObject
    {
    }
}

namespace PKP\affiliation {
    class Affiliation extends \PKP\core\DataObject
    {
    }
}

namespace PKP\doi {
    class Doi extends \PKP\core\DataObject
    {
    }
}

namespace PKP\submissionFile {
    class SubmissionFile extends \PKP\core\DataObject
    {
        public const SUBMISSION_FILE_DEPENDENT = 17;
    }

    class Collector
    {
        public function filterBySubmissionIds(?array $submissionIds): self
        {
            return $this;
        }

        public function filterByFileStages(?array $fileStages): self
        {
            return $this;
        }

        public function includeDependentFiles(bool $includeDependentFiles = true): self
        {
            return $this;
        }

        /**
         * @return list<SubmissionFile>
         */
        public function getMany(): array
        {
            return [];
        }
    }
}

namespace APP\submissionFile {
    class Repository
    {
        public function get(int $fileId, ?int $submissionId = null): ?\PKP\submissionFile\SubmissionFile
        {
            return null;
        }

        public function getCollector(): \PKP\submissionFile\Collector
        {
            return new \PKP\submissionFile\Collector();
        }
    }
}

namespace APP\galley {
    class Galley extends \PKP\core\DataObject
    {
    }

    class Collector
    {
        public function filterByPublicationIds(?array $publicationIds): self
        {
            return $this;
        }

        /**
         * @return list<Galley>
         */
        public function getMany(): array
        {
            return [];
        }
    }

    class Repository
    {
        public function getCollector(): Collector
        {
            return new Collector();
        }
    }
}

namespace APP\facades {
    class Repo
    {
        public static function publication(): \APP\publication\Repository
        {
            return new \APP\publication\Repository();
        }

        public static function submission(): \APP\submission\Repository
        {
            return new \APP\submission\Repository();
        }

        public static function submissionFile(): \APP\submissionFile\Repository
        {
            return new \APP\submissionFile\Repository();
        }

        public static function galley(): \APP\galley\Repository
        {
            return new \APP\galley\Repository();
        }
    }
}

namespace APP\core {
    class Application
    {
        public static function getContextDAO(): \PKP\context\ContextDAO
        {
            return new \PKP\context\ContextDAO();
        }

        public static function get(): self
        {
            return new self();
        }

        public function getRequest(): \PKP\core\Request
        {
            return new \PKP\core\Request();
        }
    }
}

namespace PKP\core {
    class PKPApplication
    {
        public const ROUTE_PAGE = 1;
    }

    class Request
    {
        public function getDispatcher(): Dispatcher
        {
            return new Dispatcher();
        }
    }

    class Dispatcher
    {
        public function url(mixed ...$arguments): string
        {
            return 'https://journal.example.test/article/view/1';
        }
    }
}

namespace PKP\file {
    class PrivateFileManager
    {
        public function getBasePath(): string
        {
            return '';
        }
    }
}

namespace PKP\db {
    class PluginSettingsDAO
    {
        public static array $settings = [];

        public function getSetting(?int $contextId, string $pluginName, string $settingName): mixed
        {
            return null;
        }

        public function getPluginSettings(?int $contextId, string $pluginName): array
        {
            return self::$settings;
        }
    }

    class DAORegistry
    {
        public static function getDAO(string $name): PluginSettingsDAO
        {
            return new PluginSettingsDAO();
        }
    }
}

namespace PKP\jobs {
    abstract class BaseJob
    {
        public $tries = 3;

        public int $backoff = 5;

        public int $timeout = 60;

        public int $maxExceptions = 3;

        public function __construct()
        {
        }

        /**
         * @phpstan-impure
         */
        public static function dispatch(mixed ...$arguments): mixed
        {
            return null;
        }

        public function fail(?\Throwable $exception = null): void
        {
        }

        abstract public function handle(): void;
    }
}

namespace Illuminate\Database\Migrations {
    abstract class Migration
    {
        public function up(): void
        {
        }

        public function down(): void
        {
        }
    }
}

namespace Illuminate\Database\Schema {
    class Blueprint
    {
        public function unsignedBigInteger(string $column): ColumnDefinition
        {
            return new ColumnDefinition();
        }

        public function unsignedInteger(string $column): ColumnDefinition
        {
            return new ColumnDefinition();
        }

        public function string(string $column, int $length = 255): ColumnDefinition
        {
            return new ColumnDefinition();
        }

        public function text(string $column): ColumnDefinition
        {
            return new ColumnDefinition();
        }

        public function timestamp(string $column): ColumnDefinition
        {
            return new ColumnDefinition();
        }
    }

    class ColumnDefinition
    {
        public function primary(): self
        {
            return $this;
        }

        public function nullable(): self
        {
            return $this;
        }

        public function default(mixed $value): self
        {
            return $this;
        }
    }
}

namespace Illuminate\Database\Query {
    class Builder
    {
        public function where(string $column, mixed $operator = null, mixed $value = null): self
        {
            return $this;
        }

        public function first(): ?object
        {
            return null;
        }

        /**
         * @param array<string, mixed> $values
         */
        public function update(array $values): int
        {
            return 0;
        }

        /**
         * @param array<string, mixed> $values
         */
        public function insertOrIgnore(array $values): int
        {
            return 0;
        }
    }
}

namespace Illuminate\Support\Facades {
    class DB
    {
        public static function table(string $table): \Illuminate\Database\Query\Builder
        {
            return new \Illuminate\Database\Query\Builder();
        }
    }

    class Schema
    {
        public static function hasTable(string $table): bool
        {
            return false;
        }

        public static function create(string $table, callable $callback): void
        {
        }

        public static function dropIfExists(string $table): void
        {
        }
    }
}

namespace {
    function __($key = null, $replace = [], $locale = null): string|array|null
    {
        return $key;
    }
}
