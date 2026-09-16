# Media

## Uploading

```php
$media = Whatsapp::uploadMedia(storage_path('app/photos/parcel.jpg'), 'image/jpeg');

Whatsapp::sendMediaMessage('15551234567', $media['id'], 'image', caption: 'Your parcel');
```

`uploadMedia` expects a MIME type such as `image/jpeg` or `application/pdf`. If you pass a category like `image`, the MIME type is detected from the file. Uploaded media IDs can be reused for 30 days.

## Downloading media customers send

Incoming images, videos, audio, documents and stickers include a media ID:

```php
use Crenspire\Whatsapp\Events\MessageReceived;

Event::listen(function (MessageReceived $event) {
    if ($event->isType('document')) {
        $path = Whatsapp::downloadMedia($event->mediaId());
    }
});
```

`downloadMedia` saves the file to the `media_storage` directory from the config, `storage/app/whatsapp-media` by default, names it after the media ID with an extension matching its type, and returns the path.

Media IDs from webhooks expire after 7 days, so download anything you want to keep.

## Other operations

```php
$info = Whatsapp::getMediaInfo($mediaId);   // url, mime_type, file_size, sha256
Whatsapp::deleteMedia($mediaId);
```

For sample media used when creating templates, see [Templates](./templates#image-video-and-document-headers).
