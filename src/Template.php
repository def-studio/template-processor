<?php

namespace DefStudio\TemplateProcessor;

use DefStudio\TemplateProcessor\Exceptions\TemplateProcessingException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\Process\Process;

/**
 * Class Template
 * @package DefStudio\TemplateProcessor
 *
 */
class Template
{
    protected string $temporary_directory;

    protected string $template_file;

    protected string $compiled_file;

    protected TemplateProcessor $template_processor;

    public function __construct(string $template_file = '')
    {
        $this->template_file = $template_file;
    }

    public function from($template_file): self
    {

        $this->template_file = $template_file;

        return $this;
    }

    public function compile(array $data): self
    {
        foreach ($data as $key => $content) {
            if (is_array($content)) {
                $this->clone($key, count($content), $content);
            } else {
                $this->set($key, $content);
            }
        }

        return $this;
    }

    public function set(string $key, string $value): self
    {
        $this->template_processor()->setValue($key, $value);

        return $this;
    }

    public function clone(string $block_name, int $times = 1, array $variable_replacements = []): self
    {
        $this->template_processor()->cloneBlock($block_name, $times, true, false, $variable_replacements);

        return $this;
    }

    protected function template_processor(): TemplateProcessor
    {
        return $this->template_processor ??= new TemplateProcessor($this->template_file);
    }

    /**
     * @param string $output_file
     * @return string
     * @throws TemplateProcessingException
     */
    public function to_pdf(string $output_file)
    {

        $compiled_file = $this->compiled_file();

        $temporary_directory = $this->temporary_directory();

        $process = new Process([
            'lowriter',
            '-env:UserInstallation=file:///tmp/dummy',
            '--convert-to',
            'pdf',
            '--outdir',
            $temporary_directory,
            $compiled_file
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw TemplateProcessingException::symfony_process_error($process);
        }

        $temporary_result_file = Str::of($temporary_directory)
            ->append(DIRECTORY_SEPARATOR)
            ->append($this->set_extension($this->get_filename($compiled_file), 'pdf'));

        if (!File::exists($temporary_result_file)) {
            throw TemplateProcessingException::missing_converted_file($temporary_result_file);
        }

        File::move($temporary_result_file, $this->set_extension($output_file, 'pdf'));

        return $output_file;
    }

    protected function compiled_file(): string
    {

        if (empty($this->compiled_file)) {

            if (empty($this->template_processor)) {
                $this->compiled_file = $this->template_file;
            } else {

                $compiled_file = Str::of($this->temporary_directory())
                    ->append(DIRECTORY_SEPARATOR)
                    ->append(Str::uuid())
                    ->append(".docx");

                $this->template_processor()->saveAs($compiled_file);

                $this->compiled_file = $compiled_file;
            }
        }

        return $this->compiled_file;
    }

    protected function temporary_directory(): string
    {

        if (empty($this->temporary_directory)) {
            $temp_dir = Str::of(sys_get_temp_dir())
                ->append(DIRECTORY_SEPARATOR)
                ->append("LaravelTemplateProcessor")
                ->append(DIRECTORY_SEPARATOR)
                ->append(Str::uuid());

            mkdir($temp_dir, 0777, true);

            $this->temporary_directory = $temp_dir;
        }

        return $this->temporary_directory;

    }

    protected function set_extension(string $file, string $ext): string
    {
        $path = $this->get_path($file);
        $filename_without_ext = $this->get_filename_without_ext($file);

        return Str::of($path)
            ->append(DIRECTORY_SEPARATOR)
            ->append($filename_without_ext)
            ->append('.')
            ->append($ext);
    }

    protected function get_path(string $file): string
    {
        return File::dirname($file);
    }

    protected function get_filename_without_ext(string $file)
    {
        return pathinfo($file)['filename'];
    }

    protected function get_filename(string $file): string
    {
        return File::basename($file);
    }

    protected function get_extension(string $file): string
    {
        return File::extension($file);
    }

}
