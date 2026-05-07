<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor;

enum DoctorStatus: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Fail = 'fail';
}
