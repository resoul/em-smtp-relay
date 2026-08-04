// swift-tools-version:5.9
import PackageDescription

let package = Package(
    name: "WireAuth",
    platforms: [
        .iOS(.v13),
        .macOS(.v10_15),
        .watchOS(.v8),
    ],
    products: [
        .library(
            name: "WireAuth",
            targets: ["WireAuth"]
        ),
    ],
    dependencies: [],
    targets: [
        .target(
            name: "WireAuth",
            dependencies: []
        ),
        .testTarget(
            name: "WireAuthTests",
            dependencies: ["WireAuth"]
        ),
    ]
)
