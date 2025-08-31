<?php

namespace Knuckles\Scribe\Tests\Strategies\QueryParameters;

use Closure;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Deprecated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Subgroup;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Extracting\Strategies\Metadata\GetFromMetadataAttributes;
use Knuckles\Scribe\Tests\Fixtures\TestGroupBackedEnum;
use Knuckles\Scribe\Tools\DocumentationConfig;
use PHPUnit\Framework\TestCase;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use ReflectionClass;
use ReflectionMethod;

class UseMetadataAttributesTest extends TestCase
{
    use ArraySubsetAsserts;

    /** @test */
    public function can_fetch_from_authenticated_attribute_or_authenticated_parameter()
    {
        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(MetadataAttributesTestController::class);
            $e->method = $e->controller->getMethod('a1');
        });
        $results = $this->fetch($endpoint);

        $this->assertArraySubset([
            "groupName" => "Group A",
            "groupDescription" => "A group",
            "subgroup" => "SG AA",
            "subgroupDescription" => "A subgroup",
            "title" => "Endpoint A1",
            "description" => "",
            "authenticated" => false,
        ], $results);

        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(MetadataAttributesTestController::class);
            $e->method = $e->controller->getMethod('a2');
        });
        $results = $this->fetch($endpoint);
        $this->assertArraySubset([
            "groupName" => "Group A",
            "groupDescription" => "A group",
            "subgroup" => "",
            "subgroupDescription" => "",
            "title" => "Endpoint A2",
            "description" => "Stuff",
            "authenticated" => true,
        ], $results);

        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(MetadataAttributesTestController::class);
            $e->method = $e->controller->getMethod('a3');
        });
        $results = $this->fetch($endpoint);
        $this->assertArraySubset([
            "groupName" => "Group A",
            "groupDescription" => "A group",
            "subgroup" => "",
            "subgroupDescription" => "",
            "title" => "Endpoint A3",
            "description" => "",
            "authenticated" => true,
        ], $results);

        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(MetadataAttributesTestController::class);
            $e->method = $e->controller->getMethod('b1');
        });
        $results = $this->fetch($endpoint);
        $this->assertArraySubset([
            "groupName" => "Group B",
            "groupDescription" => "",
            "subgroup" => "SG BA",
            "subgroupDescription" => "",
            "title" => "Endpoint B1",
            "description" => "",
            "authenticated" => false,
        ], $results);

        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(MetadataAttributesTestController::class);
            $e->method = $e->controller->getMethod('b2');
        });
        $results = $this->fetch($endpoint);
        $this->assertArraySubset([
            "groupName" => "Users",
            "groupDescription" => "",
            "subgroup" => "Admins",
            "subgroupDescription" => "",
            "title" => "Endpoint B2",
            "description" => "",
            "authenticated" => false,
        ], $results);

        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(MetadataAttributesTestController2::class);
            $e->method = $e->controller->getMethod('c1');
        });
        $results = $this->fetch($endpoint);
        $this->assertArraySubset([
            "authenticated" => true,
        ], $results);
        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(MetadataAttributesTestController2::class);
            $e->method = $e->controller->getMethod('c2');
        });
        $results = $this->fetch($endpoint);
        $this->assertArraySubset([
            "authenticated" => false,
        ], $results);

        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(MetadataAttributesTestController3::class);
            $e->method = $e->controller->getMethod('c1');
        });
        $results = $this->fetch($endpoint);
        $this->assertArraySubset([
            "title" => "Endpoint C"
        ], $results);

        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(MetadataAttributesTestController4::class);
            $e->method = $e->controller->getMethod('c1');
        });
        $results = $this->fetch($endpoint);
        $this->assertArraySubset([
            "deprecated" => true,
        ], $results);

        $endpoint = $this->endpoint(function (ExtractedEndpointData $e) {
            $e->controller = new ReflectionClass(MetadataAttributesTestController5::class);
            $e->method = $e->controller->getMethod('c1');
        });
        $results = $this->fetch($endpoint);
        $this->assertArraySubset([
            "deprecated" => true,
        ], $results);
    }

    protected function fetch($endpoint): array
    {
        $strategy = new GetFromMetadataAttributes(new DocumentationConfig([
            "auth" => ["default" => true]
        ]));
        return $strategy($endpoint, []);
    }

    protected function endpoint(Closure $configure): ExtractedEndpointData
    {
        $endpoint = new class extends ExtractedEndpointData {
            public function __construct(array $parameters = [])
            {
            }
        };
        $configure($endpoint);
        return $endpoint;
    }
}


#[Group("Group A", "A group")]
#[Authenticated(false)]
class MetadataAttributesTestController
{
    #[Subgroup("SG AA", "A subgroup")]
    #[Endpoint("Endpoint A1")]
    public function a1()
    {
    }

    #[Endpoint("Endpoint A2", "Stuff", authenticated: true)]
    public function a2()
    {
    }

    #[Endpoint("Endpoint A3")]
    #[Authenticated]
    public function a3()
    {
    }

    #[Group("Group B")]
    #[Subgroup("SG BA")]
    #[Endpoint("Endpoint B1")]
    public function b1()
    {
    }

    #[Group(TestGroupBackedEnum::Users)]
    #[Subgroup(TestGroupBackedEnum::Admins)]
    #[Endpoint("Endpoint B2")]
    public function b2()
    {
    }
}

#[Authenticated]
class MetadataAttributesTestController2
{
    public function c1()
    {
    }

    #[Unauthenticated]
    public function c2()
    {
    }
}


#[Endpoint("Endpoint C")]
class MetadataAttributesTestController3
{
    public function c1()
    {
    }
}

#[Deprecated]
class MetadataAttributesTestController4
{
    public function c1()
    {
    }
}

class MetadataAttributesTestController5
{
    #[Deprecated]
    public function c1()
    {
    }
}
