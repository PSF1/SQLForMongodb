# SQL For MongoDB

[![Build Status](https://travis-ci.org/PSF1/SQLForMongodb.svg?branch=master)](https://travis-ci.org/PSF1/SQLForMongodb)

This library only convert MySQL queries to MongoDB languaje. It's the first step to get compatibility betwen tradicional PHP solutions with MongoDB without change de bissnes logic.

A second goal is the hability to build a Query API in cluster form, balancing load betwen many servers. And at time, dividing the MongoDB load with a relation one to one (SQLForMongoDB node <--> MongoDB node)

## Implemented:

* SELECT: Allow complex WHERE logic, ORDER BY, LIMIT and COUNT.

## Roadmap:

* SELECT ... GROUP BY
* SELECT ... IN <subquery>
* CREATE
* INSERT
* UPDATE
* DELETE
* LEFT JOIN
* UNION
* SHOW

## SELECT's allowed operators:
!, -, ^, *, /, DIV, %, MOD, -, +, =, <=>, >=, >, <=, <, <>, !=, LIKE, REGEXP, IN, NOT, &&, AND, ||, OR

## SELECT's disalowed operators:

If you know a example of translation of this operators, please, open a new issue.

INTERVAL, BINARY, COLLATE, ~, <<, >>, &, |, IS, BETWEEN, CASE, WHEN, THEN, ELSE, XOR, :=

## Before open a issue:

Please, before open a new issue search the correct translation in MongoDB. Thanks