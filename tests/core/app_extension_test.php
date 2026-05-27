<?php declare(strict_types=1);

namespace tests\fixtures\ext {
    interface sample_service {
        public function value(): string;
    }

    class low_service implements sample_service {
        public function value(): string {
            return 'low';
        }
    }

    class high_service implements sample_service {
        public function value(): string {
            return 'high';
        }
    }

    class decorated_service implements sample_service {
        public function __construct(
            private readonly sample_service $inner,
            private readonly string $label,
        ) {}

        public function value(): string {
            return $this->inner->value() . ':' . $this->label;
        }
    }
}

namespace {
    use skim\core\app;
    use tests\fixtures\ext\decorated_service;
    use tests\fixtures\ext\high_service;
    use tests\fixtures\ext\low_service;
    use tests\fixtures\ext\sample_service;

    describe('app extension service binding', function(): void {
        test('class-string binding resolves through the container', function(): void {
            $app = app::test_instance();
            $app->bind(sample_service::class, high_service::class);

            expect($app->make(sample_service::class))->toBeInstanceOf(high_service::class);
        });

        test('higher priority binding replaces lower priority binding', function(): void {
            $app = app::test_instance();
            $app->bind(sample_service::class, low_service::class, priority: 10);
            $app->bind(sample_service::class, high_service::class, priority: 100);

            expect($app->make(sample_service::class)->value())->toBe('high');
        });

        test('lower priority binding cannot replace higher priority binding', function(): void {
            $app = app::test_instance();
            $app->bind(sample_service::class, high_service::class, priority: 100);
            $app->bind(sample_service::class, low_service::class, priority: 10);

            expect($app->make(sample_service::class)->value())->toBe('high');
        });

        test('decorators are applied in priority order', function(): void {
            $app = app::test_instance();
            $app->bind(sample_service::class, low_service::class);
            $app->decorate(sample_service::class, fn(sample_service $service): sample_service => new decorated_service($service, 'first'), priority: 10);
            $app->decorate(sample_service::class, fn(sample_service $service): sample_service => new decorated_service($service, 'second'), priority: 100);

            expect($app->make(sample_service::class)->value())->toBe('low:first:second');
        });

        test('freeze prevents service and route mutation', function(): void {
            $app = app::test_instance();
            $app->freeze();

            expect(fn() => $app->bind(sample_service::class, low_service::class))->toThrow(\LogicException::class);
            expect(fn() => $app->router->get('/late', fn(): string => 'late'))->toThrow(\LogicException::class);
        });
    });
}
