<?php

namespace AJUR\FSNews\Media\Workers;

use AJUR\FSNews\Media;
use AJUR\FSNews\MediaInterface;
use AJUR\Wrappers\GDWrapper;
use Arris\Entity\Result;
use Arris\Path;
use Psr\Log\LoggerInterface;

class Youtube
{
    //
    // getYoutubeVideoPreview(id) -> result

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Загружает с ютуба название видео. Точно работает с видео, с shorts не проверялось.
     *
     * @param string $video_id
     * @param string $default
     * @return Result [title]
     */
    public function getYoutubeVideoTitle(string $video_id, string $default = ''):Result
    {
        $r = new Result();
        $r->title = $default;

        //@todo: curl?
        $video_info = @file_get_contents("http://youtube.com/get_video_info?video_id={$video_id}");

        if (!$video_info) {
            $r->error("Invalid [http://youtube.com/get_video_info] response");
            return $r;
        }

        parse_str($video_info, $vi_array);
        $r->video_info = $video_info;

        if (!array_key_exists('player_response', $vi_array)) {
            $r->error("No [player_response] in youtube answer");
            return $r;
        }

        $video_info = json_decode($vi_array['player_response']);
        $r->player_response = $vi_array['player_response'];

        if (is_null($video_info)) {
            $r->error("Can't decode player_response from youtube answer");
            return $r;
        }

        $r->title = $video_info->videoDetails->title ?: $default;

        return $r;
    }

    /**
     * @param $url - URL скачиваемого видео
     * @param $fn_default_preview - путь к дефолтному превью, для админки 47 = Path::create(config('PATH.WEB'))->join("/frontend/images/")->joinName('youtube_video_emptypreview.jpg')->toString()
     * @return Result
     */
    public function getYoutubeVideoPreview($url, $fn_default_preview):Result
    {
        $result = new Result();

        try {
            $url_matches = parse_url($url);

            if (array_key_exists('query', $url_matches) && preg_match("/v=([A-Za-z0-9\_\-]{11})/i", $url_matches["query"], $match_result)) {
                // это youtube.com/?v=
                $video_url_hash = $match_result[1];
            } elseif (strpos($url, '/shorts/') > 0 && preg_match("/\/([A-Za-z0-9\_\-]{11})/i", $url, $match_result)) {
                // это youtube shorts
                $video_url_hash = $match_result[1];
            } elseif (strpos($url, 'youtu.be') > 0 && preg_match("/youtu\.be\/([A-Za-z0-9\_\-]{11})/i", $url, $match_result)) {
                // это короткая ссылка youtu.be/?v=
                $video_url_hash = $match_result[1];
            } else {
                // в противном случае URL нераспознан
                throw new \RuntimeException("Передан URL неизвестного типа" . $url );
            }

            $result->addMessage("Для загрузки передан корректный URL [{$url}]");

            $storage_path = Media::getAbsoluteResourcePath('youtube');
            Media::validatePath($storage_path);

            $target_filename = Media::generateNewFile($storage_path);  //@todo: если мы решим добавлять суффикс к имени файла - то можно указать его при вызове
            $target_file = "{$storage_path}/{$target_filename}";

            $result->addMessage("Сгенерировано новое уникальное имя файла: " . $target_file);

            // работаем с ютубом через CURL (напрямую, не через библиотеку, это некошерно, но...)
            $target_file_handler = fopen($target_file, 'w+');// Try to load high quality preview
            $source_url = "https://i.ytimg.com/vi/{$video_url_hash}/hqdefault.jpg";
            $curl = new \Curl\Curl();
            $curl->setOpt(CURLOPT_FILE, $target_file_handler);
            $curl->setOpt(CURLOPT_SSL_VERIFYHOST, 2);
            $curl->setOpt(CURLOPT_SSL_VERIFYPEER, true);
            $curl->get($source_url);
            $httpCode = $curl->getOpt(CURLINFO_RESPONSE_CODE);
            $curl->close();
            if (404 === $httpCode) {
                $source_url = "https://i.ytimg.com/vi/{$video_url_hash}/mqdefault.jpg";

                $curl = new \Curl\Curl();
                $curl->setOpt(CURLOPT_FILE, $target_file_handler);
                $curl->setOpt(CURLOPT_SSL_VERIFYHOST, 2);
                $curl->setOpt(CURLOPT_SSL_VERIFYPEER, true);
                $curl->get($source_url);
                $httpCode = $curl->getOpt(CURLINFO_RESPONSE_CODE);
                $curl->close();

                if (404 == $httpCode) {
                    $source_url = $fn_default_preview;
                }
            } else {
                // иначе ставим $source_url = имени файла
                $source_url = $target_file;
            }
            fclose($target_file_handler);

            $result->setData('thumbnails', []);

            foreach (Media::$convert_sizes['youtube'] as $size => $params) {
                $prefix = $params['prefix'];
                GDWrapper::getFixedPicture($source_url, "{$storage_path}/{$prefix}{$target_filename}", $params['maxWidth'], $params['maxHeight'], $params['quality']);
                $this->logger->debug('Generating image', [$source_url, "{$storage_path}/{$prefix}{$target_filename}", $params['maxWidth'], $params['maxHeight'], $params['quality']]);

                $result->addData('thumbnails', [[
                    'file'      =>  "{$storage_path}/{$prefix}{$target_filename}",
                    'width'     =>  $params['maxWidth'],
                    'height'    =>  $params['maxHeight'],
                    'quality'   =>  $params['quality']
                ]]);
            }

            $result->setData([
                'url'               =>  $url,
                'target_filename'   =>  $target_filename,
                'target_file'       =>  $target_file,
                'status'        =>  'pending',
                'type'          =>  MediaInterface::MEDIA_TYPE_YOUTUBE
            ]);

        } catch (\RuntimeException | \Exception $e) {
            $result->error($e->getMessage());
        }

        return $result;
    }

}