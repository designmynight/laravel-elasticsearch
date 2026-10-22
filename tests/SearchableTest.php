<?php

namespace Tests;

class SearchableTest extends TestCase
{
    public function testSearchIndexOverrideDoesNotCreateAnEloquentAttributeOrDynamicProperty(): void
    {
        $model = new SearchableTestModel();
        $model->setSearchIndex('booking_archive');

        $this->assertSame('booking_archive', $model->getSearchIndex());
        $this->assertArrayNotHasKey('searchIndex', $model->toArray());
        $this->assertSame('booking_archive', $model->onSearchConnection(function ($clone) {
            return $clone->getSearchIndex();
        }, $model));
    }
}
