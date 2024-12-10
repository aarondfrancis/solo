<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */
if (!function_exists('array_to_splqueue')) {
    /**
     * Converts an array or ArrayAccess object to an SplQueue.
     *
     * @param  array|ArrayAccess  $array  The array to convert.
     * @return SplQueue The resulting SplQueue.
     */
    function array_to_splqueue(array|ArrayAccess $array): SplQueue
    {
        $queue = new SplQueue;
        foreach ($array as $element) {
            $queue->enqueue($element);
        }

        return $queue;
    }
}

if (!function_exists('splqueue_to_array')) {
    /**
     * Converts an SplQueue to an array.
     *
     * @param  SplQueue  $queue  The SplQueue to convert.
     * @return array The resulting array.
     */
    function splqueue_to_array(SplQueue $queue): array
    {
        // Convert the SplQueue to an array
        return iterator_to_array($queue, false);
    }
}
