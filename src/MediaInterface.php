<?php

namespace AJUR\FSNews;

interface MediaInterface
{
    const DICTIONARY_FULL = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz';

    const DICTIONARY = '0123456789abcdefghijklmnopqrstuvwxyz';

    const DICTIONARY_LENGTH = 36;

    const MEDIA_TYPE_TITLE = 'titles';
    const MEDIA_TYPE_VIDEO = 'videos';
    const MEDIA_TYPE_PHOTOS = 'photos';
    const MEDIA_TYPE_AUDIO = 'audios';
    const MEDIA_TYPE_FILE = 'files';
    const MEDIA_TYPE_YOUTUBE = 'youtube';



}