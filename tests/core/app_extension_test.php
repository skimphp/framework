<?php declare(strict_types=1);

namespace Tests\Fixtures\Ext {
    interface SampleService {
        public function value(): string;
    }

    class LowService implements \Tests\Fixtures\Ext\SampleService {
        public function value(): string {
            return 'low';
        }
    }

    class HighService implements \Tests\Fixtures\Ext\SampleService {
        public function value(): string {
            return 'high';
        }
    }

    class DecoratedService implements \Tests\Fixtures\Ext\SampleService {
        public function __construct(
            private readonly \Tests\Fixtures\Ext\SampleService $inner,
            private readonly string $label,
        ) {}

        public function value(): string {
            return $this->inner->value() . ':' . $this->label;
        }
    }
}

namespace {
    use Skim\Core\App;
    use Tests\Fixtures\Ext\DecoratedService;
    use Tests\Fixtures\Ext\HighService;
    use Tests\Fixtures\Ext\LowService;
    use Tests\Fixtures\Ext\SampleService;

    describe('app extension service binding', function(): void {
        test('class-string binding resolves through the container', function(): void {
            $app = \Skim\Core\App::testInstance();
            $app->bind(\Tests\Fixtures\Ext\SampleService::class, \Tests\Fixtures\Ext\HighService::class);

            expect($app->make(\Tests\Fixtures\Ext\SampleService::class))->toBeInstanceOf(\Tests\Fixtures\Ext\HighService::class);
        });

        test('higher priority binding replaces lower priority binding', function(): void {
            $app = \Skim\Core\App::testInstance();
            $app->bind(\Tests\Fixtures\Ext\SampleService::class, \Tests\Fixtures\Ext\LowService::class, priority: 10);
            $app->bind(\Tests\Fixtures\Ext\SampleService::class, \Tests\Fixtures\Ext\HighService::class, priority: 100);

            expect($app->make(\Tests\Fixtures\Ext\SampleService::class)->value())->toBe('high');
        });

        test('lower priority binding cannot replace higher priority binding', function(): void {
            $app = \Skim\Core\App::testInstance();
            $app->bind(\Tests\Fixtures\Ext\SampleService::class, \Tests\Fixtures\Ext\HighService::class, priority: 100);
            $app->bind(\Tests\Fixtures\Ext\SampleService::class, \Tests\Fixtures\Ext\LowService::class, priority: 10);

            expect($app->make(\Tests\Fixtures\Ext\SampleService::class)->value())->toBe('high');
        });

        test('decorators are applied in priority order', function(): void {
            $app = \Skim\Core\App::testInstance();
            $app->bind(\Tests\Fixtures\Ext\SampleService::class, \Tests\Fixtures\Ext\LowService::class);
            $app->decorate(\Tests\Fixtures\Ext\SampleService::class, fn(\Tests\Fixtures\Ext\SampleService $service): \Tests\Fixtures\Ext\SampleService => new \Tests\Fixtures\Ext\DecoratedService($service, 'first'), priority: 10);
            $app->decorate(\Tests\Fixtures\Ext\SampleService::class, fn(\Tests\Fixtures\Ext\SampleService $service): \Tests\Fixtures\Ext\SampleService => new \Tests\Fixtures\Ext\DecoratedService($service, 'second'), priority: 100);

            expect($app->make(\Tests\Fixtures\Ext\SampleService::class)->value())->toBe('low:first:second');
        });

        test('freeze prevents service and route mutation', function(): void {
            $app = \Skim\Core\App::testInstance();
            $app->freeze();

            expect(fn() => $app->bind(\Tests\Fixtures\Ext\SampleService::class, \Tests\Fixtures\Ext\LowService::class))->toThrow(\LogicException::class);
            expect(fn() => $app->router->get('/late', fn(): string => 'late'))->toThrow(\LogicException::class);
        });
    });
}
