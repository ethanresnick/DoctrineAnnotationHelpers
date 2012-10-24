To work with this library in Symfony, simply add the following to your config.yml file:

parameters:
    annotations.reader.class: ERD\AnnotationHelpers\PowerReader
    annotations.file_cache_reader.class: ERD\AnnotationHelpers\FileCachePowerReader

That will substitute the built-in annotation reader service (and it's caching wrapper), for the extended
reader offered by this library.