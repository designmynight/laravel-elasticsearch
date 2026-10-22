<?php

namespace Tests;

class SearchableTest extends TestCase
{
    public function testSearchIndexOverrideIsAvailableOnTheSearchConnectionClone(): void
    {
        $model = new SearchableTestModel();
        $this->assertSame($model, $model->setSearchIndex('booking_archive'));

        $this->assertSame('booking_archive', $model->getSearchIndex());
        $this->assertSame('booking_archive', $model->onSearchConnection(function ($clone) {
            return $clone->getSearchIndex();
        }, $model));
    }
}
