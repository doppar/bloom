<p align="center">
    <a href="https://doppar.com" target="_blank">
        <img src="https://raw.githubusercontent.com/doppar/doppar/7138fb0e72cd55256769be6947df3ac48c300700/public/logo.png" width="400">
    </a>
</p>

<p align="center">
<a href="https://github.com/doppar/bloom/actions/workflows/tests.yml"><img src="https://github.com/doppar/bloom/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/doppar/bloom"><img src="https://img.shields.io/packagist/dt/doppar/bloom" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/doppar/bloom"><img src="https://img.shields.io/packagist/v/doppar/bloom" alt="Latest Stable Version"></a>
<a href="https://github.com/doppar/bloom/blob/main/LICENSE"><img src="https://img.shields.io/github/license/doppar/bloom" alt="License"></a>
</p>

## Doppar Bloom Filter - High-Performance Probabilistic Data Structure

Doppar Bloom Filter is a sophisticated, production-ready implementation of Bloom filters - space-efficient probabilistic data structures that test whether an element is a member of a set. Perfect for large-scale applications where memory efficiency and fast membership testing are critical. This library is an extended version of the `denismitr/laravel-bloom` package, adjusted for the Doppar framework.

## Documentation
Read the documentation from doppar official site [Doppar Bloom](https://doppar.com/versions/3.x/doppar-bloom.html)

---

## Features

- Storage Backends - Redis
- Configurable Accuracy - Adjustable false positive rates and memory usage
- Dual Hashing Algorithms - MD5 (consistent) and Murmur (high-performance)
- Batch Operations - Efficient bulk additions and checks
- Memory Efficient - Optimized bit manipulation and connection pooling
- Fluent API - Clean, intuitive interface for everyday use

## Perfect For

- Duplicate Detection - Prevent duplicate user actions, URLs, or content
- Cache Warming - Check if data exists before expensive database queries
- Spam Filtering - Quickly check if `email/URL` is known spam
- Recommendation Systems - Track viewed items without storing entire history
- Database Optimization - Reduce unnecessary lookups

---

## Performance Benchmarks

| Operation           | 100K Items | 1M Items | 10M Items |
| ------------------- | ---------- | -------- | --------- |
| Memory Usage        | 114 KB     | 1.14 MB  | 11.4 MB   |
| Check Speed         | 0.1 ms     | 0.15 ms  | 0.2 ms    |
| False Positive Rate | 1%         | 1%       | 1%        |

Benchmarks based on default configuration with Redis backend

---

## Contributing

Thank you for considering contributing to the Doppar framework! The contribution guide can be found in the [Doppar documentation](https://doppar.com/versions/3.x/contributions.html).

## Code of Conduct

In order to ensure that the Doppar community is welcoming to all, please review and abide by the [Code of Conduct](https://doppar.com/versions/3.x/contributions.html#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/doppar/framework/security/policy) on how to report security vulnerabilities.

## License

The Doppar framework is open-sourced software licensed under the [MIT license](LICENSE.md).
