# Sync course leaders

## Purpose

Course leaders are enrolled on course pages (pagetype=course), but they might not be enrolled on the modules (pagetype=module) that relate to those courses.

The only information we have that links Courses to Modules are student enrolments. That is, students are enrolled on both Modules and Courses.

We can infer from student Module enrolments which courses those students are enrolled on that Module A is linked to Course A, and then enrol Course Leader A on Module A.

e.g.

Student A is enrolled on the following:

- ABC401_2024/25 (module)
- ABC402_2024/25 (module)
- ABC403_2024/25 (module)
- XXABCMKGWDGTS (course)

Course leader A is enrolled on the following:

- ABC401_2024/25 (Module leader)
- XXABCMKGWDGTS (Course leader)

Because Student A is enrolled on those three modules and a course, any Course leader enrolled on that course will get enrolled as course leader on those three moduules. Resulting in the following:

Course leader A is enrolled on the following:

- ABC401_2024/25 (module) (Module leader & Course leader)
- ABC402_2024/25 (module) (Course leader)
- ABC403_2024/25 (module) (Course leader)
- XXABCMKGWDGTS (course) (Course leader)

## Implementation

A database table mapping Modules to Courses:

moduleshortcode, courseshortcode

Combined unique index (moduleshortcode, courseshortcode). You only need one matching pair.

For each current module
    for each student
        Fetch course enrolments
            Map module and course

For each course shortcode in mapping table
    Enrol Course leader as Course leader on related Module

Do we ever remove a Course leader?
    No need, Modules have unique instances

Run as a task, perhaps running once a day. We would need this so we don't enrol a Course leader on a Module if that Module isn't yet templated, if that enrolment is deleted, it will reappear the next day.

Only process for the current academic year.

Expire enrolments after 2 years?

