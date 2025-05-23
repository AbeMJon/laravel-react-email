<?php

namespace Maantje\ReactEmail;

use Maantje\ReactEmail\Exceptions\NodeNotFoundException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class Renderer extends Process
{
    /**
     * @param string $view
     * @param array $data
     * @throws NodeNotFoundException
     */
    private function __construct(string $view, array $data = [])
    {
        $templateDirectory = config('react-email.template_directory');
        $path = $templateDirectory . '/' . $view;

        $path = realpath($path);
        if ($path === false) {
            throw new \InvalidArgumentException('View file not found: ' . $templateDirectory . '/' . $view);
        }

        $path = str_replace('\\', '/', $path);
        if (PHP_OS_FAMILY === 'Windows') {
            $path = 'file:///' . ltrim($path, '/');
        }

        parent::__construct([
            $this->resolveNodeExecutable(),
            config('react-email.tsx_path') ?? base_path('node_modules/tsx/dist/cli.mjs'),
            __DIR__ . '/../render.tsx',
            $path,
            json_encode($data),
        ], base_path());
    }

    /**
     * Calls the react-email render
     *
     * @param string $view name of the file the component is in
     * @param array $data data that will be passed as props to the component
     * @return array
     * @throws NodeNotFoundException
     */
    public static function render(string $view, array $data): array
    {
        $process = new self($view, $data);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return json_decode($process->getOutput(), true);
    }

    /**
     * Resolve the node path from the configuration or executable finder.
     *
     * @return string
     * @throws NodeNotFoundException
     */
    public static function resolveNodeExecutable(): string
    {
        if ($executable = config('react-email.node_path') ?? app(ExecutableFinder::class)->find('node'))
        {
            return $executable;
        }

        throw new NodeNotFoundException(
            'Unable to resolve node path automatically, please provide a configuration value in react-emails'
        );
    }
}
